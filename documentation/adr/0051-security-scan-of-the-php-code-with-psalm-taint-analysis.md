# 0051. Scan the PHP code for security problems with the taint analysis of Psalm

- Status: Accepted
- Date: 2026-11-18
- Issue: #1559
- Pull request: #1657

## Context

CodeQL scans the JavaScript, the TypeScript and the workflows of this repository. It does not support PHP, and the server code is where the data and the access rules live. PHPStan (Larastan, at its highest level) checks the types: it does not follow untrusted data from where it enters to where it is used.

Two kinds of tool were on the table:

1. A taint analysis: it follows data from a source (a request, a response of another server) to a sink (a query, a shell command, an HTML answer, a header, an outgoing request) and reports a path without a check on the way. Psalm does this for PHP, and its Laravel plugin knows the sources and sinks of the framework.
2. A scanner with pattern rules (Semgrep, its fork Opengrep): it finds known bad patterns in one file. It is fast and easy to extend, but it does not follow data across functions and classes, and the public rule sets for PHP and Laravel are small and old.

A first run of Psalm 7.10 with `psalm/plugin-laravel` 4.22 on `main` took about 75 seconds on 8 cores. It found three paths, all from the discovery document of the OpenID Connect provider to a request and to a redirect. They are safe by design: the operator configures the provider, the document must name that issuer, it comes over https with a checked certificate and without a redirect, and each endpoint must be https (ADR 0045). A probe file with a raw `echo` of request data, a query built from `Request::input()` and a shell command built from `Request::query()` gave the expected results, so the analysis sees the Laravel sources.

## Decision

1. Psalm runs only for its taint analysis. PHPStan stays the type checker: `psalm.xml` uses the lowest error level, and `tools/security/psalm-taint.sh` keeps only the results whose rule starts with `Tainted`.
2. The workflow `security-php.yml` runs the scan on every pull request, on every push to `main`, and every Monday (new rules can find something in code that did not change). A result fails the job.
3. The report goes to code scanning as SARIF (category `psalm-taint`), next to the alerts of CodeQL. A pull request from a fork cannot send it: the failed job is the signal there.
4. A result that is safe by design gets an annotation in the code, at the place of the check, with the reason in the docblock: `@psalm-taint-escape <kind>`. There is no baseline file: a baseline hides results without a reason next to the code.
5. A result can be a security problem. It is handled as `SECURITY.md` and the security rules of `AGENTS.md` say: never in a public issue, a public pull request or a commit message before the fix is merged.

## Consequences

1. A pull request that sends untrusted data to a sink without a check fails, with the path in the log of the job and in code scanning.
2. An escape annotation is a security decision: it needs a review like the code of a check, and its docblock says why the data is safe.
3. Psalm and its plugin are development dependencies (28 packages). Dependabot updates them with the other Composer packages; a new major version of Psalm can change the results.
4. The job is not a required check of the branch protection yet. The owner decides when it becomes one.
5. Rule added to `documentation/conventions/rules.md`: no `@psalm-taint-escape` without the reason in the same docblock.

## Alternatives considered

1. Semgrep or Opengrep with the public PHP rules: no data flow across functions, and few, old rules for Laravel. It can come later for patterns that a taint analysis cannot see (for example a route without a policy).
2. Psalm as a second type checker at a strict level: hundreds of type results that PHPStan already covers in its own way, for no security gain.
3. A baseline of the first results: it would hide the reason of each accepted path. An annotation keeps the reason next to the check.
4. A paid service (GitHub code security for PHP does not exist; commercial scanners): not needed while an open source tool covers the need.
