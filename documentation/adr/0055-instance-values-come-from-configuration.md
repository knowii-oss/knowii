# 0055. The values of one instance come from configuration

- Status: Accepted
- Date: 2026-11-19
- Issue: #1653
- Pull request: #1683

## Context

A person who hosts the platform must be able to replace every value that describes one instance without a change of the code. After #1643 (the external account service), the code still held such values as defaults: the operator in `config/legal.php`, the publisher, the contact address and the links of the footer in a JSON file of the shared library, a person as the publisher in the structured data of every page, a name in `humans.txt`, an address in the seeder, and a host in the user agent of the capture. A self-hosted instance that changed nothing named another operator in its invitation emails and in its footer.

The comparison of twelve platforms ([`documentation/design/instance-values/comparison.md`](../design/instance-values/comparison.md)) shows that none of them lets an instance run in production under a name that is not its own without a setup step, and that the ones without a setup step read these values from configuration.

The owner decided on 2026-11-18 (the comment of #1653):

1. In production the application refuses to start when the operator or the contact address is empty. Local and test environments use neutral placeholders (`Example Operator`, `contact@example.com`).
2. The legal texts come only from `storage/app/legal/*.md`. A missing file answers 404 and its links are hidden. The repository keeps neutral templates, which are never served.
3. The legal texts of the hosted instance leave `resources/markdown/` for a folder of this repository (`deploy/`), which the deploy copies. The guard test of #1643 skips that folder.
4. The other values (publisher, contact, footer and social links, structured data) come from configuration and are hidden when empty. The shared JSON file keeps only the data of the project, `humans.txt` names the project, the seeder reads the address of its first user from the environment. No setup wizard: `SELF_HOSTING.md` gets one table of every instance value.

The work has two parts. This record covers both decisions, and the first part builds decisions 1 and 4. The second part (the legal texts, decisions 2 and 3) follows in its own pull request and adds its details here.

## Decision

### The two required values

1. `config/legal.php` holds `operator` (`LEGAL_OPERATOR`) and `contact_email` (`LEGAL_CONTACT_EMAIL`). No default names an operator or an address. The sub-processors stay in this file until the second part.
2. A value that is empty or blank is not set (`KEY=` in `.env` gives an empty text, not a default). When a value is not set and `APP_ENV` is not `production`, the configuration uses a placeholder: `Constants::INSTANCE_PLACEHOLDER_OPERATOR` (`Example Operator`) and `Constants::INSTANCE_PLACEHOLDER_CONTACT_EMAIL` (`contact@example.com`). In production a value that is not set stays empty.
3. `App\Support\InstanceValues::missing()` returns the environment names to set. Outside production the list is empty. In production a value is missing when it is empty, when it equals its placeholder in any letter case (a copied example file must not pass), or, for the address, when it is not an email address.
4. The message is one text, with the missing names joined by " and ": `This instance is not configured. Set LEGAL_OPERATOR and LEGAL_CONTACT_EMAIL in the environment. See SELF_HOSTING.md, section "Instance values".`

### The start check

5. "Production" means `app()->isProduction()`. The image sets `APP_ENV=production`, so an acceptance instance needs the two values too.
6. HTTP: the global middleware `App\Http\Middleware\RefuseUnconfiguredInstance` is prepended to the global stack: it runs before every middleware of the application and of the framework (a test checks the order; only a middleware that a package prepends later, like the one of Inertia for deferred callbacks, runs before it and reads nothing). When values are missing, it answers `503` with the message as plain text and `Cache-Control: no-store`, and writes the message to the log at the level `critical` once a minute. Every address answers so, `/up` too: the health check of Docker fails, `docker compose up --wait` fails, and the other services never start, because they wait for a healthy `app` service.
7. Console: the listener `App\Listeners\RefuseUnconfiguredInstance` hears `CommandStarting`. When values are missing and the command is one of `Constants::INSTANCE_CHECKED_COMMANDS` (`queue:work`, `queue:listen`, `schedule:work`, `schedule:run`, `reverb:start`), it throws `App\Exceptions\InstanceNotConfiguredException`: artisan prints the message and exits with 1.
8. Every other artisan command runs: `key:generate`, `storage:link`, `package:discover`, the cache commands, `optimize`, `migrate`, `app:health`, `about`, the export commands. An operator can install and repair an instance that is not configured yet.

### The optional values

9. `config/instance.php` holds them. Each value is trimmed text, and empty means hidden.

| Key                | Environment               | Used by                                                 | When empty                         |
| ------------------ | ------------------------- | ------------------------------------------------------- | ---------------------------------- |
| `publisher.name`   | `INSTANCE_PUBLISHER_NAME` | The footer line `© <year> <name>.`, the structured data | No `©` text, no `publisher` member |
| `publisher.url`    | `INSTANCE_PUBLISHER_URL`  | The structured data                                     | No `url` of the publisher          |
| `footer.note`      | `INSTANCE_FOOTER_NOTE`    | The text after the `©` text                             | Not shown                          |
| `footer.links`     | `INSTANCE_FOOTER_LINKS`   | The links of the "Community" column of the footer       | No extra link                      |
| `social_links`     | `INSTANCE_SOCIAL_LINKS`   | The icon links of the footer, `sameAs`                  | No icon, no `sameAs`               |
| `seed.admin_email` | `SEED_ADMIN_EMAIL`        | `DatabaseSeeder`                                        | The default `admin@example.com`    |

10. `INSTANCE_FOOTER_LINKS` holds entries `Label=https://address`, separated by commas and split at the first `=`. `INSTANCE_SOCIAL_LINKS` holds entries `key=https://address`, where `social_link_<key>` is in `Constants::SOCIAL_MEDIA_LINK_PROPERTIES`. `InstanceValues` parses both. An entry with an empty label, an unknown key or an address that is not `https://` is left out, with one warning in the log, like a wrong contact of `security.txt`. The same holds for a publisher address that is not `https://`. Every configuration value is narrowed with `is_string`. The warning is written once a day for one wrong value, not once for each page, because every page reads these values. A part without `=` is the rest of an address that a comma cut (`%2C` is the way to write a comma): the whole entry is left out, never shown as half an address. A second footer link with the same label, and a social link that is in the list twice, is left out too.
11. `InstanceValues::pageProp()` returns `contactEmail` (text), `publisher` and `footerNote` (text or null), `footerLinks` (a list of `label` and `href`) and `socialLinks` (a list of `property` and `href`). `HandleInertiaRequests` shares it lazily as `instance`, and `InertiaSharedProps` types it as `InstancePageProp`.

### The front end

12. `libs/common/src/lib/metadata.json` keeps only `repositoryUrl`, the data of the project. No code reads another key.
13. `buildFooterLinkGroups(instance)` returns the link columns of the footer in their order. "Community" holds the configured links, then "Contact us" when the contact address is not empty. A column without a link is left out. The two links of one instance that the column held are gone: the hosted instance sets them in `INSTANCE_FOOTER_LINKS`.
14. The bottom line of the footer shows `© <year> <publisher>.` when the publisher is set, then the note when it is set, and nothing when both are missing. The country sentence that the line held is now `INSTANCE_FOOTER_NOTE`. The mail icon shows only with a contact address. The repository icon stays. One icon link shows per social link, with the icon and the name of its property. Without the `instance` prop the footer hides all of these.
15. `/contact` answers 404 when the contact address is empty. Else it renders the `Contact` page, which reads the address from the prop.
16. The account deletion texts read the address from the prop. Without one they say "the operator of this site", the words of `AccountDeletion::UNAVAILABLE_MESSAGE`.

### Server pages and files

17. The structured data of `app.blade.php` is built by `InstanceValues::structuredData()` and printed with `json_encode` and the flags that escape `<`, `>`, `&`, `'` and `"`, so a value cannot close the script block. Members: `@context`, `@type` `WebSite`, `name` (`app.name`), `description`, `url` (`app.url`), and `publisher` only with a publisher name: `@type` `Organization`, `name`, `url` when set, `sameAs` when social links exist. The publisher was a person: it is now an organisation.
18. `public/humans.txt` names the project and its contributors.
19. `DatabaseSeeder` creates its first user as `Example Admin` with `instance.seed.admin_email`. `documentation/api/.env.example` uses the same default.
20. `Constants::CAPTURE_USER_AGENT` ends with the address of the repository: the project, not one instance.
21. The invitation email keeps `legal.operator`. The comments and the tests use `example.com` hosts and "the owner of the instance".

### The guard

22. `tests/Unit/InstanceNeutralNamesTest.php` also refuses the operator, its founder, its company number, its street address and a host of the hosted instance.
23. It reads `app`, `config`, `database`, `lang`, `routes`, `resources/views`, `apps/knowii/src`, `libs/common/src` and `tests`, and the files `public/humans.txt`, `.env.example` and `.env.production.example`. It skips no file. It never reads `deploy/`: a test checks that no entry starts with `deploy`.
24. One exception until the second part: `tests/Feature/LegalPagesTest.php`, which checks the words of the hosted texts. `resources/markdown/` is not read yet.

## Consequences

1. A production instance that is updated without `LEGAL_OPERATOR` and `LEGAL_CONTACT_EMAIL` does not start. `SELF_HOSTING.md` says it in step 8 and in "Docker", and `.env.production.example` holds both names, empty.
2. The hosted instance sets its operator, its contact address, its publisher, its footer note, its footer links and its social links in its environment, to look as before.
3. An instance that sets no optional value shows a footer without a publisher line and without social icons, and structured data without a publisher. Nothing names another operator.
4. A test that switches to production (`detectEnvironment`) configures the instance first (`configuredInstance()` in `tests/Pest.php`): the placeholders of the test environment count as not set in production.
5. A new value that describes one instance is a key of `config/instance.php` (or `config/legal.php` when the law requires it), a row of the table of `SELF_HOSTING.md`, and a line of both example files. It never gets a default that names an operator, an address or a host. The guard fails when one comes back.
6. A wrong entry of a list value is logged each time the value is read, like a wrong contact of `security.txt`: an operator sees it in the log until the value is fixed.

## Alternatives considered

1. A setup wizard on the first run (as some forum and blog platforms do): it needs a state "not installed", a first administrator and a screen to keep safe from the first visitor. The operators of the platform have shell access, and the owner decided for a table in `SELF_HOSTING.md`.
2. The values and the legal texts in the database, edited in an administration screen: it needs that screen, its permissions and a table, and a text of the law would change without a review or a history. Files and environment values are in the backups and the deploy scripts that an operator already has.
3. A neutral default in production (the placeholders everywhere): an instance would run in public and send invitation emails in the name of "Example Operator", with a contact address that reaches nobody. A refusal to start is strict, and every acceptance instance needs the two values, but it is safer than an instance that names the wrong operator or none.
4. A check at the start of the container only (the entrypoint): an instance on a host without Docker would not get it. The check in the application covers both, and the health check of Docker reports it.
5. A refusal of every artisan command: an operator could not generate a key, build the caches or migrate before the two values are set, and the entrypoint of the image runs these commands first.
