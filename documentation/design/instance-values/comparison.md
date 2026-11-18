# Instance values: how other platforms do it (#1653)

## The question

Knowii is hosted by one company, and anyone can host it. Some values of the hosted instance are defaults in the code. How do twelve platforms handle these values? For each one: where the operator name and the contact come from, what a new instance shows, how legal texts are supplied, and where the vendor keeps its own texts. Facts come from source code (November 2026) and official documentation.

## Comparison

| Platform   | Operator and contact                                        | New instance shows                                               | Legal texts                                               | Vendor's hosted texts                               |
| ---------- | ----------------------------------------------------------- | ---------------------------------------------------------------- | --------------------------------------------------------- | --------------------------------------------------- |
| Discourse  | Admin settings, empty by default                            | No ToS or privacy page (404), no links                           | Text in the admin (a topic) or a link to an outside page  | Outside the code (vendor site)                      |
| Mastodon   | Admin settings, empty by default                            | Built-in privacy policy template, no terms                       | Text in the admin. Terms come from a form-filled template | In the database of each server                      |
| Lemmy      | Setup page or config file, then admin                       | Setup page, no legal page                                        | One Markdown field in the admin                           | In the database                                     |
| Ghost      | Setup wizard                                                | Title "Ghost", "Coming soon" sample content                      | None in core: the owner writes pages                      | Vendor site, not in the code                        |
| Forem      | First user onboarding, `COMMUNITY_NAME` env                 | Name "New Forem", generic terms and privacy templates            | Built-in templates, replaced by admin pages               | Admin pages in the database                         |
| Flarum     | Web installer                                               | "A new Flarum forum"                                             | None in core: an extension adds terms                     | No vendor hosting                                   |
| Zulip      | `--email` install flag, required                            | Nothing until the admin email is set                             | Markdown files in a directory set in config               | In the repository, in a separate `corporate` folder |
| Mattermost | System Console                                              | Name "Mattermost", links to the vendor's terms and privacy pages | Links to outside pages, or text in the admin (paid)       | Vendor pages are the defaults                       |
| Synapse    | `homeserver.yaml` (`server_name` required, `admin_contact`) | No policy at all                                                 | Template files on disk, configured in YAML                | Server config, outside the code                     |
| Element    | `config.json`                                               | Points to matrix.org                                             | Links to outside pages                                    | `element.io/` config folder in the repository       |
| GitLab     | Admin settings                                              | No terms, no link                                                | Markdown text in the admin, versioned                     | Admin settings, plus `Gitlab.com?` checks in code   |
| Nextcloud  | Theming settings                                            | Name "Nextcloud", no imprint or privacy link                     | Links to outside pages, or an app with text in the admin  | No central instance                                 |
| Plausible  | Env vars (`BASE_URL` required)                              | Refuses to start without `BASE_URL`                              | None: the footer links to the vendor's pages              | Vendor site, a build flag hides the hosted parts    |

## Platform by platform

1. **Discourse.** `company_name` and `contact_email` are site settings, empty by default. Until July 2026 a new site got a seeded terms topic filled with the company name and the governing law. PR 41814 removed that seeding, and on 2026-11-18 the legal settings went too. Now `/tos` and `/privacy` show a topic that the admin creates, or redirect to `tos_url` and `privacy_policy_url`. With neither, the page is a 404 and the link is hidden. Sources: `site_settings.yml`, `static_controller.rb`, `lib/discourse.rb`, PR 41814, commit 651f84b.
2. **Mastodon.** The contact settings are empty by default. The privacy policy falls back to a built-in template with `%{domain}`, which the admin can replace. Terms are optional. The admin fills a form (email, jurisdiction, law, minimum age) and a generator renders a template that refuses empty fields. Without terms, the API gives no terms link. Sources: `config/settings.yml`, `privacy_policy.rb`, `terms_of_service/generator.rb`.
3. **Lemmy.** A `setup` block in `lemmy.hjson` creates the admin and the site name at the first start. Without it, the UI shows a setup page. Legal texts are one Markdown field, `legal_information`. Its links show only when it is set. Sources: `defaults.hjson`, lemmy-ui `footer.tsx`, `legal.tsx`, `setup.tsx`.
4. **Ghost.** The setup wizard asks for the title and the owner. Until then, the title is "Ghost", with a "Coming soon" post. Core has no legal pages. Ghost(Pro) limits and billing come from a `hostSettings` config, not from code branches. Sources: `default-settings.json`, `fixtures.json`, `services/limits.ts`.
5. **Forem.** The first user becomes the admin and names the community. The name defaults to `COMMUNITY_NAME` or "New Forem". `/terms` and `/privacy` render generic built-in templates with the community name. An admin page with the slug `terms` or `privacy` replaces them. DEV's own texts are such pages. Sources: `settings/community.rb`, `pages_controller.rb`, `_terms_text.en.html.erb`.
6. **Flarum.** The web installer asks for the title and the admin. The default title is "A new Flarum forum" and the sender `noreply@localhost`. Core has no legal pages. The `fof/terms` extension adds them. Sources: `WriteSettings.php`, `fof/terms` README.
7. **Zulip.** The installer requires `--email` and `--hostname`. The email becomes the contact that users see. Policies are Markdown files in `POLICIES_DIRECTORY` (`/etc/zulip/policies` recommended). By default none are served. The docs ask operators to name their organisation in each text, to avoid confusion with Zulip Cloud. The Zulip Cloud texts are in the open repository, in `templates/corporate/policies/`. Only the deployment that enables the `corporate` app serves them. Sources: `default_settings.py`, `prod_settings_template.py`, production docs.
8. **Mattermost.** The site name defaults to "Mattermost". The terms, privacy and help links default to `mattermost.com` pages, so a new instance shows the vendor's texts. `SupportEmail` is empty. A paid feature stores a custom terms text in the database. Source: `model/config.go`.
9. **Matrix (Synapse).** `server_name` is required and `admin_contact` is null by default. Consent tracking needs `user_consent` with a `template_dir` of versioned HTML files. Sample templates are in `docs/privacy_policy_templates/`. Without this config there is no policy. The operator serves the `/.well-known/matrix/support` contact file. Sources: config documentation, `consent_tracking.md`, Matrix spec.
10. **Matrix (Element).** All instance values are in `config.json`: `brand`, the default server, `terms_and_conditions_links`, `privacy_policy_url`. The sample has no legal links. The config of app.element.io is in the repository, in `apps/web/element.io/`, apart from the sample. Sources: `docs/config.md`, `element.io/app/config.json`.
11. **GitLab.** An admin can enforce terms. The text is Markdown in the admin settings, and each change is a version that users accept again. Without a text there is no link. GitLab.com uses the same settings. SaaS-only code checks `Gitlab.com?`, which compares the configured URL to `https://gitlab.com`. Sources: `terms.md`, `help_page.md`, `lib/gitlab.rb`.
12. **Nextcloud.** The name defaults to "Nextcloud". Theming sets the name, `imprintUrl` and `privacyUrl`. Empty URLs are hidden. An optional app stores terms per language and country in the admin. No central vendor instance exists. Sources: `ThemingDefaults.php`, `terms_of_service` README.
13. **Plausible.** Community Edition refuses to start without `BASE_URL` or `SECRET_KEY_BASE`. It has no instance legal texts. The footer links to plausible.io/privacy and /terms in both editions. A compile-time `ce?`/`ee?` flag hides the hosted-only parts. Sources: `runtime.exs`, `plausible.ex`, `footer.html.heex`.

## Patterns

1. **Instance values live in the database or in config, never in the code.** All twelve platforms do this for the operator and the contact.
2. **An empty value hides the feature.** Discourse, Lemmy, GitLab and Nextcloud show no legal page and no link until the operator supplies one. Forem and Mastodon serve a generic template. Mattermost and Plausible fall back to the vendor's own pages, the fault this issue wants to avoid.
3. **Only the values a server needs to run are required.** Zulip, Plausible and Synapse refuse to install or start without them. None refuses to start because a legal text is missing.
4. **Templates are opt-in.** Mastodon's generator refuses an empty field. Synapse keeps samples in `docs/`. Discourse dropped its seeded terms in 2026.
5. **The vendor's texts sit apart from the defaults.** Zulip and Element keep them in the public repository, in a folder that only their deployment uses. The others keep them in their database or on their own site.
6. **Wizards ask for the name and the admin, never for legal data.**

## Recommendation for Knowii

1. **Unset values.** No value of one instance has a default in `app/`, `config/`, `database/`, `apps/` or `libs/`. In production, the application refuses to start when `LEGAL_OPERATOR` or the contact address is empty, like Plausible. The invitation email must name the operator (GDPR article 14), so an empty value is a legal fault. Optional values (footer links, social links, structured data fields) are hidden when empty. Local and test environments use neutral placeholders ("Example Operator", `contact@example.com`).
2. **Legal texts.** Keep files in `storage/app/legal/` (the Zulip pattern) as the only source in production. A missing file gives a 404, and the footer and sign-up hide its link. Move the current texts out of `resources/markdown/`. Keep neutral templates in the repository (for example `resources/legal-templates/`) with `draft: true` and `{{operator}}` and `{{contact}}` placeholders. They are never served. Do not move the texts to the database: files keep the front matter, the placeholders and a reviewed history.
3. **Texts of the hosted instance.** Put them in a separate folder of the public repository, for example `deploy/knowii.net/legal/`, and let the deploy copy them to `storage/app/legal/`. This is the Zulip and Element pattern. The texts are public on the site anyway, so a private repository protects nothing. Wording changes stay in reviewed pull requests. The guard test of #1643 excludes this folder.
4. **Other values.** `metadata.json` keeps the project data only. The publisher, the contact and the footer links come from config, shared with the pages through Inertia. The structured data reads the same config. `humans.txt` names the project, not the operator. The seeder reads the admin address from env and uses `example.com` elsewhere.
5. **Setup.** No wizard now. `SELF_HOSTING.md` gets one table of every instance value: required or not, and the effect when empty. The start check covers the required ones.

Trade-offs:

1. A refusal to start is strict, and every test deploy needs the two values. It is safer than an instance that names the wrong operator.
2. A 404 for a missing privacy policy lets an operator run without one. A warning in the admin area and in `php artisan about` reduces that risk.
3. Hosted texts in the public repository travel with every fork. A private repository avoids this, at the cost of a second repository in the deploy.
4. Files are harder to edit than an admin field. Knowii operators have shell access.

## Sources

1. https://github.com/discourse/discourse/blob/main/config/site_settings.yml
2. https://github.com/discourse/discourse/blob/main/app/controllers/static_controller.rb
3. https://github.com/discourse/discourse/blob/main/lib/discourse.rb
4. https://github.com/discourse/discourse/pull/41814
5. https://github.com/discourse/discourse/commit/651f84b7e88b3a7f08d164962aa1dd70260b917e
6. https://github.com/mastodon/mastodon/blob/main/config/settings.yml
7. https://github.com/mastodon/mastodon/blob/main/app/models/privacy_policy.rb
8. https://github.com/mastodon/mastodon/blob/main/app/models/terms_of_service/generator.rb
9. https://github.com/mastodon/mastodon/blob/main/app/serializers/rest/instance_serializer.rb
10. https://github.com/LemmyNet/lemmy/blob/main/config/defaults.hjson
11. https://github.com/LemmyNet/lemmy-ui/blob/main/src/shared/components/app/footer.tsx
12. https://github.com/LemmyNet/lemmy-ui/blob/main/src/shared/components/home/legal.tsx
13. https://github.com/TryGhost/Ghost/blob/main/ghost/core/server/data/schema/default-settings/default-settings.json
14. https://github.com/TryGhost/Ghost/blob/main/ghost/core/server/data/schema/fixtures/fixtures.json
15. https://github.com/forem/forem/blob/main/app/models/settings/community.rb
16. https://github.com/forem/forem/blob/main/app/controllers/pages_controller.rb
17. https://github.com/forem/forem/blob/main/app/views/pages/_terms_text.en.html.erb
18. https://github.com/flarum/framework/blob/2.x/framework/core/src/Install/Steps/WriteSettings.php
19. https://github.com/FriendsOfFlarum/terms
20. https://github.com/zulip/zulip/blob/main/zproject/default_settings.py
21. https://github.com/zulip/zulip/blob/main/zproject/prod_settings_template.py
22. https://zulip.readthedocs.io/en/latest/production/settings.html
23. https://zulip.readthedocs.io/en/latest/production/install.html
24. https://github.com/zulip/zulip/tree/main/templates/corporate/policies
25. https://github.com/mattermost/mattermost/blob/master/server/public/model/config.go
26. https://element-hq.github.io/synapse/latest/usage/configuration/config_documentation.html
27. https://github.com/element-hq/synapse/blob/develop/docs/consent_tracking.md
28. https://spec.matrix.org/latest/client-server-api/#getwell-knownmatrixsupport
29. https://github.com/element-hq/element-web/blob/develop/docs/config.md
30. https://github.com/element-hq/element-web/blob/develop/apps/web/element.io/app/config.json
31. https://docs.gitlab.com/administration/settings/terms/
32. https://docs.gitlab.com/administration/settings/help_page/
33. https://gitlab.com/gitlab-org/gitlab/-/blob/master/lib/gitlab.rb
34. https://github.com/nextcloud/server/blob/master/apps/theming/lib/ThemingDefaults.php
35. https://github.com/nextcloud/terms_of_service
36. https://github.com/plausible/analytics/blob/master/config/runtime.exs
37. https://github.com/plausible/analytics/blob/master/lib/plausible_web/components/layouts/footer.html.heex
