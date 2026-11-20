---
title: Privacy policy
updated: 2026-01-15
draft: false
---

# Privacy policy

Test text of a privacy policy. It is not a legal text: the tests read it in place of the texts of an instance.

## 1. Who is responsible

{{operator}} runs this site ({{site}}). Contact: [{{contact}}](mailto:{{contact}}).

The sign-in provider of this site: {{sign_in_provider}}.

## 2. Who receives the data

{{subprocessors}}

## 3. How long we keep the data

1. Sessions: {{retention.session}} after your last activity. Sign-in links: {{retention.sign_in_link}}. Password reset links: {{retention.password_reset_link}}.
2. Invitations: valid for {{retention.invitation}}, deleted {{retention.expired_invitations}} after they expire.
3. API tokens: until they expire ({{retention.api_token_default}} by default). Expired tokens are deleted {{retention.expired_api_tokens}} after their expiry date.
4. Uploaded images that no content uses: {{retention.unused_uploads}}.
5. Server logs: {{retention.logs}}. Failed background tasks: {{retention.failed_jobs}}.
6. Waitlist: at most {{retention.waitlist}}. Unconfirmed addresses: {{retention.inactive_contacts}}.
7. Backups: {{retention.backups_daily}} daily, {{retention.backups_weekly}} weekly and {{retention.backups_monthly}} monthly, for up to {{retention.backups_longest}}.
8. Notifications: {{retention.notifications_read}} after you read them, {{retention.notifications_unread}} when you never read them.
9. Answers to events: {{retention.event_answers}} after the event.

## 4. Cookies

| Name                  | Duration                                       |
| --------------------- | ---------------------------------------------- |
| `{{cookies.session}}` | {{retention.session}} after your last activity |
