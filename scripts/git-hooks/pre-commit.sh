#!/usr/bin/env bash
# Pre-commit hook: lint, format affected files, and audit dependencies.
# Invoked by Git 2.54+ config-based hooks via `.gitconfig` at the repo root.
# Replaces the previous husky + .husky/pre-commit setup.

set -euo pipefail

npm run lint
npm run format:affected
composer audit
