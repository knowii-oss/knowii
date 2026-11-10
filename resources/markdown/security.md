---
title: Security
updated: 2026-11-10
draft: true
---

# Security

Your community trusts Knowii with its conversations, its knowledge and the data of its members. This page lists what we do to protect them. It describes the Knowii instance at knowii.net, run by DeveloPassion SRL in Belgium.

## Data in the European Union

1. The servers are in Germany, at Hetzner, in the European Union.
2. DeveloPassion SRL is a Belgian company. The [privacy policy](/privacy-policy) and the [data processing agreement](/dpa) follow the GDPR.
3. The sub-processors that receive personal data are listed in the privacy policy and the DPA. Community owners hear about a new one at least 14 days before it applies, or choose it themselves when they turn on an optional feature.
4. We do not sell data, show advertising, or use tracking cookies. Our audience statistics come from Plausible, without cookies and without personal data.

## Encryption

1. Every connection uses HTTPS. Browsers must use HTTPS for knowii.net (HSTS, also for its subdomains).
2. Passwords are stored as hashes (bcrypt), never in clear text. Two-factor authentication secrets and recovery codes are encrypted.
3. Backups are encrypted before they leave the server.

## Accounts and access

1. Two-factor authentication with an authenticator app, and recovery codes.
2. Sign-in links by email are valid for {{retention.sign_in_link}} and work once.
3. Sign-in attempts are rate limited.
4. Only accounts with a verified email address can use the platform and its API.
5. API tokens give only the rights that you choose (for example "read posts"), and expire after {{retention.api_token_default}} by default. You can revoke a token at any time.
6. Every request is checked against your community, your role and your access groups. You see only the spaces that you are allowed to read, and you change only what your role allows. Automated tests check these rules, and every API route names the token rights that it needs.

## Your content

1. Posts, comments and messages are written in Markdown and turned into HTML on our server. Raw HTML is escaped and unsafe links are removed, so a member cannot run code in the browser of another member.
2. A strict Content Security Policy only allows our own scripts and styles (with a new random value on every page) and a short list of named services. No page can be shown inside a frame on another site.
3. Uploaded images are re-encoded on our server. This removes their hidden metadata, such as the location where a photo was taken.
4. YouTube and Vimeo videos load only when you press Play. Until then, these services receive nothing from your browser.
5. When a member adds a link, our server fetches the page with protections against requests to internal addresses.

## The servers

1. Only the HTTPS port is open to the internet. Administration goes through a private network, never over the public internet.
2. Cloudflare protects the platform against attacks and bots, and the public forms use a bot check.
3. Security updates of the operating system install automatically.
4. The data that we keep only for a time is deleted by daily tasks. The privacy policy lists each period.

## Open source

1. The code of Knowii is open source (AGPL-3.0) on [GitHub](https://github.com/knowii-oss/knowii). Anyone can read how it protects data.
2. Every change goes through automated checks before it is merged: tests, static analysis, a scan of the dependencies for known vulnerabilities, and GitHub CodeQL.
3. Dependabot proposes updates of the dependencies.

## Report a vulnerability

If you find a security problem, write to [contact@developassion.be](mailto:contact@developassion.be) with the steps to reproduce it. Please do not open a public issue, and give us a reasonable time to fix the problem before you tell others. We confirm that we received your report and keep you informed.

Do not access, change or delete the data of others while you test, and do not run tests that can degrade the service.
