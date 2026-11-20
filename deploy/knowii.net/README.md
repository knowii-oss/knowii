# The values of the hosted instance (knowii.net)

This folder holds what belongs to one instance of Knowii: the one that the project hosts at knowii.net. The code of the platform holds no value of one instance (#1653, [ADR 0055](../../documentation/adr/0055-instance-values-come-from-configuration.md)), and the test `tests/Unit/InstanceNeutralNamesTest.php` never reads `deploy/`.

Another instance does not use these files. They describe one operator: never serve them on another instance. An operator starts from the neutral templates of `resources/legal-templates/` ([`SELF_HOSTING.md`](../../SELF_HOSTING.md), "Legal pages").

## What is here

`legal/` holds the legal texts of the hosted instance, and its sub-processors:

| File                 | Page                                                   |
| -------------------- | ------------------------------------------------------ |
| `policy.md`          | Privacy policy (`/privacy-policy`)                     |
| `terms.md`           | Terms of service (`/terms-of-service`)                 |
| `dpa.md`             | Data processing agreement (`/dpa`)                     |
| `imprint.md`         | Imprint (`/imprint`)                                   |
| `content-policy.md`  | Content policy (`/content-policy`)                     |
| `security.md`        | Security (`/security`)                                 |
| `subprocessors.json` | The sub-processors, for the `{{subprocessors}}` tables |

1. The application reads the legal texts only from `storage/app/legal/`. It never reads this folder: the deploy copies the files.
2. The owner approved the words of these texts. A change of a word needs the approval of the owner, in a reviewed pull request (`.github/CODEOWNERS`).
3. `tests/Feature/HostedLegalTextsTest.php` checks the approved words, with `legal.path` set to this folder.
4. The other values of the hosted instance (the operator, the contact address, the publisher, the footer and the social links) are in its environment, not in this folder (`SELF_HOSTING.md`, "Instance values").

## The sub-processors

`subprocessors.json` is a JSON list of objects with `name`, `country`, `purpose`, `safeguard` and `scope` (`platform`, `community` or both), and `feature` for a service that only an optional feature uses. The privacy policy shows every entry, and the data processing agreement shows the entries with the scope `community`.

1. A pull request that adds a third-party service that receives personal data adds it to this file when the hosted instance uses it (data rule 9 of `documentation/conventions/rules.md`). The data processing agreement promises 14 days of notice to the community owners before a new sub-processor applies: tell the owner before the merge.
2. The transfer safeguard of Resend, Inc. (#1347): it is certified under the EU-US Data Privacy Framework since 2025. Its data processing addendum keeps the standard contractual clauses as the fallback, like the one of Cloudflare.

## How the texts reach an instance

The release before the one that reads the texts only from `storage/app/legal/` already reads `storage/app/legal/<file>.md` before its own file. So the texts are copied before the update, and no page answers 404 at any moment. That release ignores `subprocessors.json`.

1. Host-based, the hosted instance, once, before the update, from the root of the repository:

   ```bash
   git fetch
   mkdir -p storage/app/legal && git archive <new ref> deploy/knowii.net/legal | tar -x --strip-components=3 -C storage/app/legal
   ```

   Then, for every later update, directly after the line of its deploy script that gets the new code (`git checkout` or `git pull`), so that a changed text reaches the site with the same deploy:

   ```bash
   mkdir -p storage/app/legal && cp deploy/knowii.net/legal/*.md deploy/knowii.net/legal/subprocessors.json storage/app/legal/
   ```

2. Host-based, another operator, before the update: each legal page that it wants is a file in `storage/app/legal/`, with its own `subprocessors.json`. To start from the templates:

   ```bash
   git fetch
   mkdir -p storage/app/legal && git archive <new ref> resources/legal-templates | tar -x --strip-components=2 -C storage/app/legal
   ```

   Then rename `subprocessors.example.json` to `subprocessors.json`, replace every `[TODO: ...]` mark, add the `updated` line, and set `draft: false`. Then the commands of step 8 of `SELF_HOSTING.md`.

3. Docker: `storage/app` is the volume `storage`, and the image holds no `deploy/` folder. Before `pull`, from the folder of the Compose file:

   ```bash
   tar -c -C <folder of your texts> . | docker compose -f compose.production.yaml run --rm --no-deps -T --entrypoint sh app -c 'mkdir -p /app/storage/app/legal && tar -x -C /app/storage/app/legal'
   ```

   Then "Update" of the Docker section of `SELF_HOSTING.md`. The Compose file, the image and the entrypoint need no change. For the hosted instance, `<folder of your texts>` is `deploy/knowii.net/legal` of a checkout of the new ref. The workflow `.github/workflows/deploy-acceptance.yml` runs this command when its variable `LEGAL_TEXTS_DIR` names a folder.

A file that is copied is served at the next request: the application needs no restart.

No command removes a text: to retire a page, delete its file in `storage/app/legal/` on the server.
