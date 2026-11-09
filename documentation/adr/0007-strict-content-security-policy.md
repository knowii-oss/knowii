# 0007. Strict Content Security Policy

- Status: Accepted
- Date: 2026-11-09
- Issue: #1183
- Pull request: #1171, #1190

## Context

Members will write posts, comments and messages and upload images. If HTML ever slips through, the Content Security Policy is the last line of defense. The old policy allowed `'unsafe-inline'` styles and whole schemes (`https:`).

## Decision

1. Scripts: per-request nonce and `'strict-dynamic'`. Third-party scripts are added by our own code.
2. Styles: `'self'`, the per-request nonce and Google Fonts. No `'unsafe-inline'`. Styles set through the CSSOM (React `style` props) are allowed by browsers; `<style>` elements need the nonce from `<meta property="csp-nonce">`.
3. No directive accepts a whole scheme (`http:`, `https:`, `ws:`, `wss:`) or `*`. Every origin is listed in `config/secure-headers.php`, from configuration values (Reverb, uploads host, `CSP_*_SRC`).
4. A feature test fails when a directive accepts a whole scheme.

## Consequences

1. Embeds that inject inline styles cannot be used (the EmailOctopus form was replaced by our own form).
2. A new external origin (video host, storage bucket) is a configuration value and a reviewed change.
3. Each screen is checked in the browser for CSP violations.

## Alternatives considered

1. `'unsafe-inline'` for styles: weakens the policy against injected HTML.
2. Hashes for each inline style: brittle with a build tool.
