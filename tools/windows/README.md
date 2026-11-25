# Development on a Windows host

The setup of a Windows machine for the work on the platform: how to commit, and how to get a fast development server. The steps suppose Docker Desktop and Git Bash.

## Commit through the container

The Git hooks (Prettier, Pint, commitlint) cannot run on the Windows host. Stage on Windows, write the message to `.git/MSG`, then commit inside the container:

```bash
cp tools/windows/commit-in-container.sh .git/commit-in-container.sh
MSYS_NO_PATHCONV=1 docker compose exec -T -u sail laravel.test bash .git/commit-in-container.sh </dev/null
```

Push from Windows afterwards. Read `.git/MSG` before you overwrite it: an old message can still be there.

The container sees only the main working tree. A commit in a second git worktree fails in the hooks on the host and cannot run in the container.

A recreated container loses the Git settings of the `sail` user. Set them again before a commit:

```bash
git config --global --add safe.directory /var/www/html
git config --global user.name "<your name>"
git config --global user.email "<your address>"
```

## A fast development server

The bind mount is slow: a page can take 6 seconds or more. Three settings bring a request to about 0.5 seconds:

1. `PHP_CLI_SERVER_WORKERS=8` in `.env`.
2. `SAIL_XDEBUG_MODE=off` in `.env`.
3. OPcache for the CLI server: copy `99-dev-opcache-cli.ini` to `/etc/php/8.5/cli/conf.d/` in the container, then `docker compose restart laravel.test`. The file is lost when the container is recreated.

The ini file sets `opcache.validate_timestamps=0`, because a file check costs seconds on the mount. So the server does not see a PHP change: run `docker compose restart laravel.test` before a browser check. The tests are not affected.

## Other points

1. Run `docker compose` in Git Bash with `MSYS_NO_PATHCONV=1`.
2. Run the tests with `bash tools/test/affected.sh` (the tests of your change) or `TEST_PROCESSES=8 bash tools/test/run.sh` (the whole suite) in the container: see "Back-end tests" in `CONTRIBUTING.md`.
3. After a front-end change, run `npm run build` in the container and wait for its end before a browser check.
4. Never put PHP namespaces in a shell heredoc: the backslashes collapse. Write such files with an editor.
