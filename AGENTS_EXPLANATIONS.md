# AGENTS_EXPLANATIONS.md

This companion document records the provenance of every requirement in the
`AGENTS.md` hierarchy so that human maintainers (and reviewers) can audit the
ruleset.

## 1. CI must be green before PR
* **Why?** Mirrors Nextcloud’s documented quality gate that no PR is reviewed
  unless all checks pass.
* **Evidence**: Nextcloud contribution guide (“label ‘5 – ready for review’ only after CI passes”).

## 2. Language-aware linting
* **Why?** Avoid running heavy PHP tools on JS files and vice-versa.
* **Evidence**: Separate sections in Nextcloud docs for PHP vs JS vs CSS.

## 3. Conventional Commits
* **Why?** Enables automated changelog generation and semantic-version analysis.
* **Evidence**: Conventional Commits spec; adopted by parts of Nextcloud CI.

## 4. Changelog entries
* **Why?** Nextcloud apps use Keep-a-Changelog format.
* **Implementation**: Agents append under **Unreleased**; maintainers bump on release.

## 5. Token optimisation with `tiktoken`
* **Why?** Codex context length is finite; oversized commit messages waste context.
* **Evidence**: OpenAI cookbook on counting tokens; Codex spec emphasises efficiency.

## 6. File precedence & scoping
* **Why?** Aligns with the official AGENTS spec (deeper files override shallower ones).

## 7. No new audit gates
* **Why?** User explicitly declined new tooling; existing Composer/NPM audits are retained.

## 8. License header (AGPL-3.0-or-later)
* **Why?** Mandated by Nextcloud “License headers” section.

## 9. Network & Proxy Configuration
* **Why?** Ensures generated code snippets or CI jobs that perform HTTP requests work correctly through corporate proxies.
* **Evidence**: Internal infrastructure requirements for https://openai.com/codex integrations.

Any future modification to an `AGENTS.md` must append its rationale here and
cite the relevant upstream source.

## 10. AGENTS changes not in changelog
* **Why?** Keep the changelog focused on features.
* **Evidence**: Maintainer request during review.
