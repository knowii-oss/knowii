# 0069. Names are one line of plain text, and mails print plain text

- Status: Accepted
- Date: 2026-11-24
- Issue: none
- Pull request: none
- Amends: [0064](0064-the-session-and-the-statistics-keep-no-address-that-holds-a-secret.md) (point 11: every mail of the application is encrypted in the queue, not only a mail that holds a link), [0057](0057-the-landing-after-the-address-check-travels-in-the-link.md) (the mail of the address check goes through the queue), [0054](0054-email-delivery-the-mail-queue-the-budget-bounces-and-the-one-click-stop.md) (a notification mail is encrypted in the queue)
- Uses: [0013](0013-markdown-rendered-on-the-server.md) (Markdown is for the content of members, not for their names), [0020](0020-members-choose-visible-profile-fields.md) (the headline of a profile, the first one-line field with a cleaning)

## Context

Three facts about the texts that a person types and that are not content:

1. The pages show a name, a title or a place as text. The mails of the application are Markdown mails of the framework: the framework reads the body of a mail as Markdown after the view printed its values. A name is not Markdown: a mail must print it as the pages do.
2. A one-line text has no line break and no character that the reader does not see. The headline of a profile, the member tags and the invite links have a rule for it. Each other name and title needs the same one.
3. The mails that hold a link are queued and encrypted in the queue ([ADR 0064](0064-the-session-and-the-statistics-keep-no-address-that-holds-a-secret.md) point 11), and they keep no text of an error of the mail provider. One rule for every mail is simpler to keep than a list of the mails that need it.

## Decision

### Mails print plain text

1. `Illuminate\Mail\Markdown::withSecuredEncoding()` is on (`AppServiceProvider::boot()`). Every `{{ }}` of a Markdown mail escapes `\`, `[`, `]`, `<` and `>`, so a value makes no link and no element. It escapes no quote and no `&`: see decision 3 for an attribute.
2. A sentence of a mail view that holds a value of a person goes through `App\Support\MailText::plain()`: the name of a person, the name of a community, an address typed in a form. The sentence becomes one line (`PlainText::oneLine()`), and each punctuation character of ASCII is written as a numeric character reference. Markdown reads a character reference as the character itself, never as a part of its syntax, and the plain-text part of the mail decodes it. So the two parts of the mail show the characters that the person typed, with no link, no image, no heading, no emphasis, no list and no HTML, and with no backslash and no reference to read.
3. A value that is Markdown or HTML on purpose is written in the view itself: the address of a button (`mail::button`), a link (`[text](address)` with an address of the application), a heading of the mail. The address of a button or of a link is always an address that the application built (a route), never a text of a person. Because `{{ }}` escapes no quote in a Markdown mail, a value in an attribute or an address is escaped for HTML on purpose: a view that writes the attribute writes `{!! e($value) !!}`, and the `url` of a `mail::button` is `MailText::address($value)`.
4. A value of a person in the subject of a mail goes through `MailText::subject()`: one line, no control character. A mail goes to an address, never to an account: no header holds the name of a person.
5. `tests/Feature/MailPlainTextTest.php` sends each mail class of the application (a Mailable or a notification with `toMail()`, in any folder of `app/`) with a text that holds a link, an image, a heading, HTML, emphasis, a list and line breaks in each value of a person. A new mail class without a case fails it. It also checks the text and the address of each button and link of each mail, and that an address with both quotes stays inside its attribute in each mail view.

### Every mail goes through the queue, encrypted, and a failed mail keeps no address

6. Every mail class of the application (each class of `app/Mail`, each mail notification of `app/Notifications`, and a mail class in any other folder) implements `ShouldQueue` and `ShouldBeEncrypted`, and the first middleware of its `middleware()` is `App\Jobs\Middleware\HideMailErrorText`. So no mail is sent inside a request, the rows of `jobs` and `failed_jobs` hold no address in clear, and the text of an error of the mail provider (which can name the recipient) reaches neither `failed_jobs` nor the log.
7. A mailable is a queued mail also when a caller uses `send()`. The one job that sends a mail itself, `App\Jobs\SendInvitationImportMail` ([ADR 0061](0061-invitations-carry-access-groups-and-tags-and-come-from-a-file.md)), uses `sendNow()`: it holds the id of a row, never an address, and has `HideMailErrorText` as its first middleware too.
8. The mail of the address check (`App\Notifications\VerifyEmailAddress`) goes through the queue `default`, as the password reset mail. Its link is built by the worker, so it has the host name of `APP_URL`, as the link of every other queued mail, and its life counts from the send. The landing still comes from the session of the request ([ADR 0057](0057-the-landing-after-the-address-check-travels-in-the-link.md)).
9. `tests/Feature/MailQueuedEncryptedTest.php` finds every mail class of `app/`, in any folder: a new class without the three fails it. No file of `app/` sends a mail without a class (`Mail::raw()`, `Mail::html()`, `Mail::plain()`, `Mail::send()` with a view, a mailable of an anonymous class): such a mail has no place for these rules.

### A name is one line of plain text

10. `App\Support\PlainText` is the one cleaner. `oneLine()` is the cleaning of the headline of a profile, moved there: it removes the control characters, the direction marks and the other characters that change the direction of the text (U+200E, U+200F, U+061C, U+202A to U+202E, U+2066 to U+2069), every other format character (the category Cf: U+200B, U+2060, U+FEFF, the soft hyphen, the tag characters and the like) and the fillers that show nothing (U+115F, U+1160, U+3164, U+FFA0, U+2800, U+034F, U+17B4, U+17B5), makes one space of each run of white space and of each line break, and trims. What a normal name needs stays: a zero width joiner between two emoji, a zero width joiner or non-joiner after a letter of a script that needs it (inside a word or at its end), the Mongolian vowel separator between two Mongolian letters, and the tags of the flag of a part of a country (England, Scotland, Wales). `lines()` is for a text of several lines: it removes the control and the direction characters, and keeps the line breaks.
11. The cleaning runs in the action, before the validation counts the text (`PlainText::clean()`), not in the request and not only in the browser: an action is also called without a request. A required field with nothing left fails as an empty one.
12. The one-line fields: the name of an account (the sign-up, the profile, a sign-in provider), the location and the headline of a profile, the name of a community, of a space group, of a space and of an access group, the emoji of a space, the title of a post, of a page and of a lesson, the title, the place and the text of the cover of an event, the title of a course material and the name of its file, the name of a resource, the title, the description and the text of the cover of a captured page, the alternative text of an image, and the name of an API token.
13. The fields of several lines: the bio of a profile, and the description of a community, of a space, of an access group and of a resource.
14. A member tag and an invite link keep their own rule, which is stricter: a name with a control character or an invisible format character is refused ([ADR 0060](0060-member-tags-labels-on-members.md), [ADR 0062](0062-invite-links-a-shared-link-to-join-a-community-with-set-access-groups-and-tags.md)).
15. The migration `2026_11_24_153900_clean_the_plain_texts_of_names_and_titles` cleans the stored values, in chunks. It writes only a value that changes, so it can run again. A required value with nothing left gets a safe value: the username for the name of an account or of a profile, the slug for the name of a community or of a space, "Group" for a space group or an access group, "Untitled" for a title or the name of a resource, "Image" for an alternative text, "Token" for an API token, "file" for the name of a file. A name that must be unique (a shared community, an access group in its community) and that another row has after the cleaning gets a number at its end. A slug never changes.

## Consequences

1. A name with Markdown characters shows in a mail as the person typed it. The framework removes one backslash of two that follow each other in the HTML part of a mail: a text with `\\` shows `\` there.
2. A member who types an invisible character or a line break in a name gets the name without it, with no error. A name that looks like another name because of letters of another script is still possible: that is a product rule, not a cleaning.
3. A queued mail is sent a moment after the request. An account that asks for the mail of the address check again sees no error of the mail provider: the worker tries the mail, and a failure is in `failed_jobs` with the class of the error only.
4. The migration cannot be undone: the characters that it removes are stored nowhere.
5. After an update of the code, the compiled views are built again (`php artisan view:clear`, or a new container), as for every change of a view.
6. Two rules in `documentation/conventions/rules.md` (Architecture 12 and 13): a new mail is queued, encrypted and hides the text of an error, and a new one-line field goes through the cleaner.

## Alternatives considered

1. Only the switch of the framework (`withSecuredEncoding()`): it is about the characters of a link and of HTML. A name must also not read as a heading, as emphasis, as a list or as several paragraphs.
2. Backslashes before each Markdown character of a value: the plain-text part of the mail would show the backslashes.
3. Plain mails without Markdown: the buttons and the layout of the framework are worth keeping, and the notification mails will hold rendered excerpts.
4. A rule that refuses a name with a control or an invisible character: a person who pastes a name cannot see the character that the error is about. The cleaning gives the name that the person sees.
5. The cleaning in a middleware of the request, for every field: a body in Markdown and a password must keep their characters, and an action that a job or a command calls would skip it.
