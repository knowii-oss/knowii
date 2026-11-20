# Security policy

How Knowii protects the data of its communities: https://app.knowii.net/security (also `deploy/knowii.net/legal/security.md` in this repository). This file says how to report a security problem and what happens then.

## Report a vulnerability

**Never write a security problem in a public issue, a public pull request or a discussion.**

1. The first way: open the ["Security" tab of this repository](https://github.com/knowii-oss/knowii/security/advisories/new) and press "Report a vulnerability". Only the maintainers see the report.
2. The second way: write to contact@developassion.be.

A good report holds:

1. What the problem is, and what an attacker can do with it.
2. The steps to reproduce it, on your own local instance where that is possible.
3. The version or the commit, and whether the instance is the hosted one or your own.
4. How you want to be named in the credits, or that you do not want to be named.

Do not send personal data of other people, passwords or keys. Tell us where they are, not what they are.

## What you can expect

1. We confirm that we received your report in 3 working days.
2. We tell you in 10 working days whether we can reproduce the problem and how serious we think it is.
3. We keep you informed until the fix is published, and we tell you when it is.
4. We try to publish a fix for a critical or a high problem in 30 days, and for the other problems in 90 days. If we need more time, we tell you why.
5. We publish a security advisory with the fix, and we name you in it if you want that.
6. We ask you to tell nobody else about the problem before the fix is published, or before 90 days after your report, whichever comes first, unless we agree on another date.

There is no paid reward (no bug bounty).

## Scope

In scope:

1. The code of this repository.
2. The instance that the project runs at knowii.net and its subdomains.

Not in scope:

1. An instance that another person or organisation hosts. Tell its operator. If the cause is in the code of this repository, also tell us.
2. The services of other companies that the platform uses (the mail provider, the host, the code host). Tell them.
3. A problem that needs a device that is already under the control of the attacker, or an old browser without security updates.
4. The result of an automatic scanner without a proof that the problem can be used.
5. A missing best practice with no way to use it (for example a missing header on a page without data).

## Rules for testing

1. Test on your own local instance where that is possible. `CONTRIBUTING.md` says how to run one.
2. Do not read, change or delete the data of other people. Use only accounts and communities that you made yourself. If you see data of other people by accident, stop, do not keep it, and tell us.
3. Do not run tests that can slow down or stop the service (no load tests, no floods of requests, no floods of mail).
4. Do not trick people: no phishing and no other social engineering of the members, the owners of communities or the maintainers.
5. No physical attack.

If you follow these rules and report in good faith, we see your research as authorized, we do not start a legal action against you for it, and we do not ask another party to do so.

## Supported versions

Only the newest release and the `main` branch get security fixes. A person who hosts the platform updates to the newest release to get a fix.

## How a fix is published

1. The fix is in a new release, and the hosted instance gets it first.
2. A [security advisory](https://github.com/knowii-oss/knowii/security/advisories) of this repository says which versions are concerned, how serious the problem is, and what to do. A CVE number is asked for when the problem concerns the people who host the platform.
3. The changelog names the advisory.

People who host the platform: `SELF_HOSTING.md` says how to get these advisories.
