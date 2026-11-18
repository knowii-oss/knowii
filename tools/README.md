# Tools

Helper scripts for the people who work on the Knowii Platform. They are not part of the application: the production image does not hold this folder.

| Tool                            | What it does                                                                                      |
| ------------------------------- | ------------------------------------------------------------------------------------------------- |
| [`screen-atlas/`](screen-atlas) | Takes a picture of every screen, in every state, for every role, at two widths and in both themes |
| [`merge/`](merge)               | Merges a pull request only when every check is green                                              |
| [`windows/`](windows)           | The setup of a Windows machine: commit through the container, a fast development server           |

## Screen atlas

The atlas is the base of a UI review (the first one: [the review of 2026-11-17](../documentation/reviews/2026-11-17-ui-review/report.md)). It is also the way to look at a change of a color or of a shared component on every screen.

It needs the development containers, the production build of the front-end and its seed data:

```sh
# 1. The seed (local environment only; safe to run again). On Linux, through `newgrp docker`.
docker compose exec -T -u sail laravel.test php artisan tinker tools/screen-atlas/atlas-seed.php

# 2. The production build (the pictures need the build, not the dev server)
docker compose exec -T -u sail laravel.test npm run build

# 3. The pictures (the tool runs the seed again before each width and theme)
cd tools/screen-atlas
npm ci
npx playwright install chromium
node atlas.mjs
```

1. `screens.json` holds one entry per screen and state. `node atlas.mjs --only settings/ --widths 1920 --themes light` takes one part (the screens whose name starts with `settings/`; several texts are separated by commas). The other options are at the top of `atlas.mjs`.
2. The pictures and their index go to `tools/screen-atlas/atlas-out/` (not in the repository). `ATLAS_OUT` changes the place, `ATLAS_BASE_URL` the address of the application (default `http://localhost:4200`).
3. The seed makes the communities "Atlas Garden" and "Atlas Empty" and the accounts `atlas-<role>@example.com`. `atlas-unseed.php` removes them.
4. The tool refuses every request to a host that is not the application.
5. Do not run the build while a person or a test drives the application: the build deletes `public/build` for a minute.

The `a11y-*.mjs` scripts are the checks of the accessibility reviewer (axe-core, the keyboard, the accessibility tree, reflow, target size, forced colors). Each file says at its top what it checks and how to run it. They use the same seed, and they write to `a11y-out/`.

## Merge gate

`bash tools/merge/gate-merge.sh <pull request number>` waits for the checks and merges with a rebase only when every check is a success. It exists because a merge command does not stop on a failed check for a person who may bypass the branch protection.

## Windows

[`windows/README.md`](windows/README.md) says how to commit when the Git hooks cannot run on the host, and which three settings make the development server fast on a bind mount.
