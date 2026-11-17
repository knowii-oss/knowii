# Notes of the review lead (2026-11-17)

These notes win over the reviewer files where they differ.

## Checked by the lead

1. **Fixed during the review (not a finding any more)**: the production build shipped the spec files as pages, and `/user/api-tokens` answered 500. Issue #1552, pull request #1553, merged. The atlas pictures `account/api-tokens--*` show the error page of the old build.
2. **Fixed during the review**: a timer of the Markdown editor was never cleared and failed test runs now and then. Issue #1554, pull request #1556 (in CI at the time of writing).
3. **N1, confirmed in the code**: `Pages/Error.tsx` shows "403: Forbidden" and "Sorry, you are forbidden from accessing this page. ⛔" with one button to the home address. No way to join or to ask for access.
4. **N2 and W1, confirmed in the code (the same finding)**: `Pages/Profile/Partials/UpdateProfileInformationForm.tsx:204` says "Note that Apart from your username, all the information will be publicly visible to other Knowii users". The same form gives each field its own box "Visible to the members of my communities" (line 62). The sentence is false and has a grammar error. Privacy words: the owner approves the new text.
5. **W2, partly confirmed, weight "should" (not "must")**: the account deletion form says before the box that posts, comments, resources and messages stay, "or tick the box below". With the box ticked, ADR 0043 keeps resources, pages and lessons (point 7, by design) and keeps an empty marker for a post or comment with replies of others. The box text is true; the sentence before it lets a member think that the box removes resources too. The owner approved these words on 2026-11-15 (#1344): the fix is one help line under the box, and it is his decision.
6. **M1, confirmed in the code, a product decision for the owner**: `app/Actions/Comments/DeleteComment.php` deletes a comment with its replies ("Its replies are deleted with it (cascade)"). ADR 0043 (point 33.2) says that an ordinary delete is unchanged, so it was a known choice inside the account deletion work. Chat messages and the account deletion keep a marker in the same case. Keep it as "must decide before launch": one member can remove the words of others.
7. **M3, mostly decided already**: the owner decided on 2026-11-16 (#1500, pull request #1512) that the control "Private / Public" of a community stays, with help texts that say what it does today. Do not reopen that. What is new and stays a finding: the "New community" dialog shows the choice with no explanation (the same as N10). That a signed-in person who is not a member can read a public chat space is the written rule of ADR 0044: not a finding.
8. **D1 and A1, confirmed in the code (the same finding)**: `styles.css:47` says about `--color-secondary-text` (#ff1493) "large or decorative text only (2.89:1 on the background)". It is used for small text in the dark theme: links in content (`MarkdownContent.tsx:116`), "Pinned" (`PostCard.tsx:23`). The fix changes one brand color value for text in the dark theme: a proposal for the owner with before and after pictures, but its weight stays "must" (WCAG 1.4.3 on main paths in the default theme).
9. **Chat edit at 360 px (A12)**: the accessibility reviewer saw it in the browser: the edit box is not broken; the chat has a fixed height of 70vh, so the edit form shows through a message log that is too small. Join it with D9 (the chat at 360 px shows about 1.5 messages).
   10a. **M2, confirmed by the lead in a headless browser** (member, space "Garden talk", 1280 px): "New post", a title and about 190 characters of text, then Escape: the dialog closes with no question; "New post" again: the title and the text are empty. A click outside the dialog closes it the same way.
   10b. **W3, confirmed by the lead in a headless browser**: with the create request of a post answered by a 500, the only text shown is "Internal Server Error" (the dialog stays open, so the text is not lost in that case).
10. **Not checked by the lead**: M4 (the chat marks everything read at once), A3, A4, D2. Mark them "seen by one reviewer in the code or the browser", with the confidence of the reviewer.
11. **Possible defect of the atlas, not of the product**: the "Catch up" card is missing in the 1920 px dark pictures of the community home. The seed makes the member "away for nine days" and a visit clears that; the dark runs came later. Do not report it as a product finding; list it under "Limits of this review".

## Decided by the owner, do not report as findings

1. A change of the look of the product as a whole (colors, type, the shell) is a proposal for the owner, with before and after pictures. Keep the reviewers' "Proposals for the owner" in their own section.
2. Component toolkit (#657): Storybook with stories as tests; slice 1 is pull request #1555. The "Missing building blocks" lists feed slice 4 of that work.
3. Planned features with an issue are not findings (the experienced member listed them with their numbers).
4. The legal pages are owner-approved texts.

## Limits of this review (say them in the report)

1. No real screen reader ran: accessibility tree snapshots only. Chromium only.
2. Four of five reviewers worked from pictures and code, without a browser.
3. Eight of 1 076 pictures are missing; the API tokens screen was not reviewable from the atlas.
4. The moderator, admin and new-member roles were covered less than the member and the owner.
5. The review ran on local seed data, not on a real community with real load.
