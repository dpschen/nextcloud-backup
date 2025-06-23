# AGENTS.md — Root Guidelines for Codex & other AI agents

## 0. Pre-flight checklist
1. Run _all_ repo CI jobs (`make ci` or the default GitHub Actions workflow) and ensure they are ✔️ green **before opening a PR**.  
2. Confirm you’re operating inside the correct folder scope; deeper `AGENTS.md` files override this one.  
3. Use `tiktoken` to keep each commit message ≤ 200 tokens and each PR description ≤ 400 tokens.  
4. If any command in this file fails, fix the root cause and rerun _all_ checks.

## 1. Repository workflow

| Stage                    | Mandatory commands                                                         |
|--------------------------|-----------------------------------------------------------------------------|
| Lint & static analysis   | `composer run cs:check` • `vendor/bin/psalm` • `npm run lint`               |
| Test suite               | `composer run phpunit` • `npm run test`                                    |
| Meta                     | `git pull --rebase`, sign commits (`-s`), Conventional Commit titles        |
| Changelog                | Append one bullet under **Unreleased** in `CHANGELOG.md` (Keep-a-Changelog). Skip AGENTS.md updates |

Only open a PR when the local run passes **and** CI is green.

## 2. Coding standards overview

| Language     | Tool                                                                |
|--------------|---------------------------------------------------------------------|
| PHP          | `nextcloud/coding-standard` (`php-cs-fixer`)                        |
| JS / TS      | `@nextcloud/eslint-config` + `@nextcloud/eslint-plugin`             |
| CSS / HTML   | Stylelint (+ BEM rules)                                             |

Agents _must_ autodetect languages by file extension and run only the relevant toolchain.

## 3. Commit & PR etiquette

* Commit titles follow Conventional Commits and use present-tense imperative (e.g. `feat(calendar): add CalDAV discovery endpoint`).
* Keep patches review-sized: ideally **< 300 LOC**.
* The PR template (auto-inserted) expects **Why / What / How tested / Checklist**.
* Label your PR with state (`3. to review`) and type (`bug`, `enhancement`, `technical debt`).

## 4. License & authorship

All files **must** begin with the SPDX header:

```php
/**
 * @copyright <year> <Your Name>
 * @license   AGPL-3.0-or-later
 */
```


## 5. Token-budget discipline

1. Commit-message hook:

   ```bash
   npx --yes tiktoken-commitmsg --max 200
   ```

2. Prefer descriptive identifiers over long comments.  
3. Collapse reusable boiler-plate into helper functions.

## 6. File-level overrides

Place an `AGENTS.md` in a sub-folder to override these rules for everything beneath that folder.

## 7. Obligatory CI targets

```make
ci: test lint psalm
lint: phpcs eslint stylelint
test: phpunit jest
```

## 8. Fail-fast gates

Existing Composer and NPM audit stages must remain enabled; do **not** add new audit tooling and do **not** disable existing checks.

## 9. Network & Proxy Configuration

- Route outbound traffic through the proxy at `http://proxy:8080` for both HTTP and HTTPS.  
- Export environment variables: `http_proxy`, `https_proxy` (and uppercase `HTTP_PROXY`, `HTTPS_PROXY`) plus optional `NO_PROXY`.  
- Trust the proxy’s CA certificate via `$CODEX_PROXY_CERT`, and ensure tools inherit it through `PIP_CERT`, `NODE_EXTRA_CA_CERTS`, or the fallback `SSL_CERT_FILE`.  
- For troubleshooting, echo the proxy vars, run `curl https://example.com`, confirm the cert path, adjust `NO_PROXY`, and inspect any TLS/DNS errors.
