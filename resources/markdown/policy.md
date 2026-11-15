---
title: Privacy policy
updated: 2026-11-15
draft: false
---

# Privacy policy

This policy explains which personal data Knowii collects, why, how long it keeps it, and what your rights are. It applies to the Knowii platform and to the communities that it hosts.

## 1. Who is responsible

DeveloPassion SRL, 126 Route de Mons, 7130 Binche, Belgium (company number 0717.561.755, VAT BE0717561755) runs Knowii. The contact person for data protection is Sébastien Dubois, founder of DeveloPassion SRL. Contact: [contact@developassion.be](mailto:contact@developassion.be).

Knowii is for people of 16 and older. We do not knowingly keep the data of younger people: write to us and we delete the account.

DeveloPassion SRL is the controller of the data of your Knowii account. For the content and the members of a community, the owner of the community decides what happens, and DeveloPassion SRL processes the data on the owner's behalf, as described in the [data processing agreement](/dpa).

## 2. The data we collect

1. Account: your name, email address, username, password (stored as a hash, never in clear text), two-factor authentication secret and recovery codes (encrypted), avatar, the date you verified your email address, and your choice to show or hide your online status, and, when you sign in with a Knowii account, the identifier of that account and the date you connected it.
2. Profile: what you choose to add (bio, location, phone number, links to your sites and social accounts), and which of these fields other members see. Your phone number is hidden by default.
3. Communities: your memberships, your role, your access groups, the lessons of a course that you marked as done, your answers and scores in the quizzes of lessons, the members you blocked, the invitations sent to your email address, what you read in chat spaces and conversations, to show what is new, and the notifications that tell you when a member replies to you or mentions you (who wrote, about which post, comment or message, and when you saw it).
4. Your content: posts, comments, reactions, mentions, resources and their captured content, chat messages, direct messages, pages, lessons, and uploaded images and their alternative text. Uploaded images are re-encoded on our server, which removes their metadata (location, camera).
5. Technical data: the IP address and the browser of each session, the API tokens you create and the date of their last use, the token of the 'Remember me' option, your password reset requests until they expire, and counters by IP address that limit the number of requests (rate limits).
6. Waitlist and newsletter: your email address, if you join them.
7. Data that we get from other people: when a manager of a community invites you, they give us your email address. When a member mentions you, that mention is content of the community.
8. Audience statistics: page views, counted by Plausible without cookies and without a stored identifier. Our proxy at Cloudflare and Plausible see your IP address to count the view and do not keep it.

Your name, email address, username and password are required to create an account. Without them, we cannot give you one. Everything else is optional.

We do not use advertising or tracking cookies, and we do not sell personal data.

## 3. Why we use it, and on what legal basis

1. To run your account and the communities you join: the contract between you and us (GDPR art. 6(1)(b)).
2. To protect the platform (sign-in protection, rate limits, bot checks, logs, backups): our legitimate interest in a secure service (art. 6(1)(f)).
3. To send the waitlist and newsletter emails: your consent (art. 6(1)(a)). You can unsubscribe in every email.
4. To send the emails that the service needs (sign-in links, address verification, invitations): the contract.
5. To know how many people visit the pages: our legitimate interest in improving the service (art. 6(1)(f)).

## 4. Who receives the data

Members of a community see what you post in it, and the profile fields you choose to show. The owner and the administrators of a community see its member list.

What you post in a public space of a community can be read by anyone on the internet, with your name and your picture.

We use these sub-processors:

{{subprocessors}}

Some of them are in the United States. These transfers use the EU-US Data Privacy Framework or the standard contractual clauses of the European Commission. Write to contact@developassion.be for a copy of these safeguards.

Videos in course lessons come from YouTube or Vimeo. Your browser contacts YouTube or Vimeo only when you press Play: from then on, their own privacy policy applies.

When a member adds a link to a resources space, our server fetches the page to capture it. The site sees the address of our server, not yours.

## 5. How long we keep the data

1. Account and profile: while your account exists. When you delete your account, we delete your personal data at once. Your posts, comments, resources and messages stay where you wrote them, under the name "Deleted member". When you delete your account, you can also delete your posts, comments and messages. Afterwards, write to contact@developassion.be to have content removed.
2. Content: until its author, a moderator or the owner deletes it, or the community is deleted. A deleted message leaves an empty marker ("This message was deleted"), without its text.
3. Sessions: {{retention.session}} after your last activity. Sign-in links: {{retention.sign_in_link}}. Password reset links: {{retention.password_reset_link}}.
4. Invitations: valid for {{retention.invitation}}, deleted {{retention.expired_invitations}} after they expire.
5. API tokens: until they expire ({{retention.api_token_default}} by default) or you revoke them. Expired tokens are deleted {{retention.expired_api_tokens}} after their expiry date.
6. Uploaded images that no content uses: {{retention.unused_uploads}}.
7. Server logs: {{retention.logs}}. Failed background tasks: {{retention.failed_jobs}}.
8. Waitlist: until the waitlist closes, at most {{retention.waitlist}} after you join it. Unconfirmed and unsubscribed addresses of the waitlist and the newsletter: {{retention.inactive_contacts}} after their last change.
9. Backups: we keep {{retention.backups_daily}} daily, {{retention.backups_weekly}} weekly and {{retention.backups_monthly}} monthly encrypted backups. Deleted data can stay in a backup for up to {{retention.backups_longest}}, until that backup is deleted.
10. Online status: never stored. Other members see it only while you are connected, and only if you allow it in your profile settings.
11. Notifications: {{retention.notifications_read}} after you read them, {{retention.notifications_unread}} when you never read them.

## 6. Cookies

Knowii uses only the cookies and the browser storage that the service needs, so there is no consent banner.

| Name                                                                                 | Kind            | Purpose                                                        | Duration                                       |
| ------------------------------------------------------------------------------------ | --------------- | -------------------------------------------------------------- | ---------------------------------------------- |
| `{{cookies.session}}`                                                                | Cookie          | Keeps you signed in                                            | {{retention.session}} after your last activity |
| `XSRF-TOKEN`                                                                         | Cookie          | Protects your forms against cross-site requests                | The same duration as the session               |
| `remember_web_*`                                                                     | Cookie          | Keeps you signed in between visits when you tick "Remember me" | 400 days, or until you sign out                |
| The theme, the comment threads that you collapsed, the skin tone of the emoji picker | Browser storage | It never leaves your browser                                   | It stays until you clear it                    |

The sign-up, waitlist and newsletter forms use Cloudflare Turnstile to block bots. Cloudflare processes your IP address and data about your browser for this check.

## 7. Security

All traffic uses HTTPS. Passwords are hashed, and two-factor authentication is available. The pages use a strict Content Security Policy. Backups are encrypted before they leave the server. Only the people who run Knowii have access to the servers.

## 8. Your rights

You can ask us for a copy of your data, its correction or its deletion, limit or object to its use, and receive it in a machine-readable format. You can withdraw your consent at any time. You correct your profile and delete your account yourself in the settings. For the other requests, write to [contact@developassion.be](mailto:contact@developassion.be). We answer within one month.

We make no automated decisions about you, and we do no profiling.

For data in a community, you can also contact its owner.

If you think that we do not respect your rights, you can file a complaint with the Belgian Data Protection Authority (Autorité de protection des données / Gegevensbeschermingsautoriteit, rue de la Presse 35, 1000 Brussels, [www.dataprotectionauthority.be](https://www.dataprotectionauthority.be)).

## 9. People who are not members

Content in a community, such as a post or a captured article, can name people who are not members of Knowii. The owner of the community decides about that content. If it is about you, write to contact@developassion.be: we pass your request to the owner and remove the content when the law requires it.

## 10. Changes

When this policy changes, we update the date at the top. For important changes, we inform the members by email before the change applies.
