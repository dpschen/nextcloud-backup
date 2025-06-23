<?php

declare(strict_types=1);

/**
 * @copyright 2024 ChatGPT
 * @license   AGPL-3.0-or-later
 */

namespace OCA\Backup\Service;

use Exception;
use OC;
use OC\Files\AppData\Factory;
use OCA\Backup\AppInfo\Application;
use OCA\Backup\Db\PointRequest;
use OCA\Backup\Exceptions\ArchiveNotFoundException;
use OCA\Backup\Exceptions\ExternalAppdataException;
use OCA\Backup\Exceptions\ExternalFolderNotFoundException;
use OCA\Backup\Exceptions\RestoringPointNotFoundException;
use OCA\Backup\Model\ChunkPartHealth;
use OCA\Backup\Model\ExternalFolder;
use OCA\Backup\Model\RestoringChunk;
use OCA\Backup\Model\RestoringChunkPart;
use OCA\Backup\Model\RestoringData;
use OCA\Backup\Model\RestoringHealth;
use OCA\Backup\Model\RestoringPoint;
use OCA\Backup\Tools\Exceptions\InvalidItemException;
use OCA\Backup\Tools\Exceptions\SignatoryException;
use OCA\Backup\Tools\Traits\TDeserialize;
use OCA\Backup\Tools\Traits\TNCLogger;
use OCA\Backup\Wrappers\AppDataRootWrapper;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

class RestoringPointScanner {
	use TNCLogger;
	use TDeserialize;

	private PointRequest $pointRequest;
	private ExternalFolderService $externalFolderService;
	private ChunkService $chunkService;
	private PackService $packService;
	private MetadataService $metadataService;
	private FilesService $filesService;
	private ConfigService $configService;

	private ?AppDataRootWrapper $appDataRoot = null;

	public function __construct(
		PointRequest $pointRequest,
		ExternalFolderService $externalFolderService,
		ChunkService $chunkService,
		PackService $packService,
		MetadataService $metadataService,
		FilesService $filesService,
		ConfigService $configService
	) {
		$this->pointRequest = $pointRequest;
		$this->externalFolderService = $externalFolderService;
		$this->chunkService = $chunkService;
		$this->packService = $packService;
		$this->metadataService = $metadataService;
		$this->filesService = $filesService;
		$this->configService = $configService;
	}

	/**
	 * @return RestoringPoint[]
	 * @throws ExternalFolderNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function scanFoldersFromAppData(): array {
		$this->initBackupFS();
		$result = [];
		foreach ($this->appDataRoot->getFolders() as $pointId) {
			try {
				$result[] = $this->generatePointFromAppData($pointId);
			} catch (Exception $e) {
			}
		}
		return $result;
	}

	/**
	 * @param string $pointId
	 *
	 * @return RestoringPoint
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RestoringPointNotFoundException
	 * @throws SignatoryException
	 */
	public function generatePointFromAppData(string $pointId): RestoringPoint {
		$tmp = new RestoringPoint();
		$tmp->setId($pointId);
		$this->initBaseFolder($tmp);

		$folder = $tmp->getBaseFolder();

		try {
			$file = $folder->getFile(MetadataService::METADATA_FILE);
		} catch (NotFoundException $e) {
			throw new RestoringPointNotFoundException('could not find restoring point in appdata');
		}

		try {
			$point = $this->deserializeJson($file->getContent(), RestoringPoint::class);
		} catch (InvalidItemException $e) {
			throw new RestoringPointNotFoundException('invalid metadata');
		} catch (NotFoundException $e) {
			throw new RestoringPointNotFoundException('cannot access ' . MetadataService::METADATA_FILE);
		} catch (NotPermittedException $e) {
			throw new RestoringPointNotFoundException('cannot read ' . MetadataService::METADATA_FILE);
		}

		$this->generateHealth($point);
		$this->pointRequest->save($point);

		return $point;
	}

	/**
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 * @throws ExternalFolderNotFoundException
	 */
	private function initBackupFS(bool $force = false): void {
		if (!is_null($this->appDataRoot)) {
			return;
		}

		if (!class_exists(OC::class)) {
			return;
		}

		$this->appDataRoot = new AppDataRootWrapper();

		try {
			$externalAppData = $this->getExternalAppData();
			$this->appDataRoot->setExternalFolder($externalAppData);
		} catch (ExternalAppdataException $e) {
			/** @var Factory $factory */
			$factory = OC::$server->get(Factory::class);
			$this->appDataRoot->setSimpleRoot($factory->get(Application::APP_ID));
		}

		if ($force) {
			return;
		}

		$path = '/';

		try {
			$this->appDataRoot->newFolder($path);
		} catch (NotPermittedException $e) {
		}

		$folder = $this->appDataRoot->getFolder($path);
		$folder->newFile(PointService::NOBACKUP_FILE, '');
		$folder->newFile(PointService::NOINDEX_FILE, '');
	}

	/**
	 * @return ExternalFolder
	 * @throws ExternalAppdataException
	 * @throws ExternalFolderNotFoundException
	 */
	private function getExternalAppData(): ExternalFolder {
		$externalAppdata = $this->configService->getAppValueArray(ConfigService::EXTERNAL_APPDATA);

		if (empty($externalAppdata)) {
			throw new ExternalAppdataException();
		}

		$external = new ExternalFolder();
		try {
			$external->import($externalAppdata);
		} catch (InvalidItemException $e) {
			throw new ExternalAppdataException('invalid ExternalFolder');
		}

		$this->externalFolderService->initRootFolder($external);

		return $external;
	}

	/**
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	private function initBaseFolder(RestoringPoint $point): void {
		if ($point->hasBaseFolder()) {
			return;
		}

		$this->initBackupFS();

		try {
			$folder = $this->appDataRoot->newFolder('/' . $point->getId());
		} catch (NotPermittedException $e) {
			$folder = $this->appDataRoot->getFolder('/' . $point->getId());
		}

		$folder->newFile(PointService::NOBACKUP_FILE, '');
		$folder->newFile(PointService::NOINDEX_FILE, '');

		$point->setAppDataRootWrapper($this->appDataRoot);
		$point->setBaseFolder($folder);
	}

	/**
	 * Update $point with it, but also returns the generated RestoringHealth
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws SignatoryException
	 */
	private function generateHealth(RestoringPoint $point): void {
		$this->initBackupFS();
		$this->initBaseFolder($point);

		$health = new RestoringHealth();
		$globalStatus = RestoringHealth::STATUS_OK;
		foreach ($point->getRestoringData() as $data) {
			foreach ($data->getChunks() as $chunk) {
				if ($chunk->hasParts()) {
					$this->generateHealthPacked($health, $point, $data, $chunk, $globalStatus);
					continue;
				}

				$chunkHealth = new ChunkPartHealth();

				$status = $this->generateChunkHealthStatus($point, $chunk);
				if ($status !== ChunkPartHealth::STATUS_OK) {
					$globalStatus = 0;
				}

				$chunkHealth->setDataName($data->getName())
						   ->setChunkName($chunk->getName())
						   ->setStatus($status);
				$health->addPart($chunkHealth);
			}
		}

		if ($globalStatus === RestoringHealth::STATUS_OK && $point->getParent() !== '') {
			try {
				$this->pointRequest->getById($point->getParent());
			} catch (RestoringPointNotFoundException $e) {
				$globalStatus = RestoringHealth::STATUS_ORPHAN;
			}
		}

		$health->setStatus($globalStatus)
			   ->setChecked(time());
		$point->setHealth($health);
	}

	private function generateHealthPacked(
		RestoringHealth $health,
		RestoringPoint $point,
		RestoringData $data,
		RestoringChunk $chunk,
		int &$globalStatus
	): void {
		foreach ($chunk->getParts() as $part) {
			$partHealth = new ChunkPartHealth(true);
			$status = $this->generatePartHealthStatus($point, $chunk, $part);
			if ($status !== ChunkPartHealth::STATUS_OK) {
				$globalStatus = 0;
			}

			$partHealth->setDataName($data->getName())
					   ->setChunkName($chunk->getName())
					   ->setPartName($part->getName())
					   ->setStatus($status);
			$health->addPart($partHealth);
		}
	}

	private function generateChunkHealthStatus(RestoringPoint $point, RestoringChunk $chunk): int {
		try {
			$checksum = $this->chunkService->getChecksum($point, $chunk);
			if ($checksum !== $chunk->getChecksum()) {
				return ChunkPartHealth::STATUS_CHECKSUM;
			}
			return ChunkPartHealth::STATUS_OK;
		} catch (ArchiveNotFoundException $e) {
			return ChunkPartHealth::STATUS_MISSING;
		}
	}

	private function generatePartHealthStatus(RestoringPoint $point, RestoringChunk $chunk, RestoringChunkPart $part): int {
		try {
			$checksum = $this->packService->getChecksum($point, $chunk, $part);
			if ($checksum !== $part->getCurrentChecksum()) {
				return ChunkPartHealth::STATUS_CHECKSUM;
			}
			return ChunkPartHealth::STATUS_OK;
		} catch (ArchiveNotFoundException $e) {
			return ChunkPartHealth::STATUS_MISSING;
		}
	}
}
