# Version History Automation

This folder contains scripts to build a dynamic changelog section for the docs.

## Files

- `generate-version-history.js`
  - Reads `VERSION`
  - Reads git commit history
  - Classifies commits into release/feature/fix/docs/etc.
  - Writes `documentation/assets/data/version-history.json`

- `install-post-commit-hook.js`
  - Installs a git `post-commit` hook
  - Regenerates version history on every commit
  - Stages `documentation/assets/data/version-history.json`

## Usage

Generate history manually:

```bash
node version-history/generate-version-history.js
```

Install auto-update hook:

```bash
node version-history/install-post-commit-hook.js
```

NPM shortcuts:

```bash
npm run docs:version-history
npm run version-history:install-hook
```
