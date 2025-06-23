# AGENTS.md — Rules specific to PHP backend

## Mandatory commands before commit

```bash
composer install
composer run psalm
composer run cs:check
```

If `cs:check` fails, fix the reported issues and restage. Fail-fast on any Psalm error of level ≤ 2.

## Unit tests

```bash
composer run phpunit -- --testsuite Unit
```

Agents must reach a 100 % pass rate before committing.
