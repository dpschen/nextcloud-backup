# AGENTS.md — Rules specific to JavaScript / TypeScript

## Lint & type-check

```bash
npm ci
npm run lint
npm run type-check     # vue-tsc --noEmit
```

## Tests

```bash
npm run test           # vitest
```

The suite bails on the first failure.

## Build-size token budget

Keep comment blocks within Vue/TS sources ≤ 150 tokens.

```bash
npx --yes tiktoken-filecheck src/**/*.vue --max 150
```
