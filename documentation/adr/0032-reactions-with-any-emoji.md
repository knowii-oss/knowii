# 0032. Reactions use any emoji of an allow-list, chosen in our own picker

- Status: Accepted
- Date: 2026-11-10
- Issue: #1204
- Pull request: #1312

## Context

Reactions (#1176) had five fixed kinds (like, love, insightful, funny, celebrate). Sébastien wants any emoji with an excellent picker, on posts, comments, chat messages and direct messages. Constraints: the strict Content Security Policy (third-party pickers inject styles), the main bundle size (#1170), keyboard and screen reader support, and no free text in a field that every member sees.

## Decision

1. `reactions.emoji` (string) replaces `reactions.kind`. The migration maps the five kinds to 👍 ❤️ 💡 😄 🎉, which stay as the quick reactions (`Emoji::QUICK`, `QUICK_REACTIONS`). The API field is `emoji` (no backward compatibility, no production yet).
2. The server accepts an emoji only when it is in `resources/emoji/allowed.json`: every emoji up to Unicode Emoji 15.1, with the five single skin tones. `scripts/generate-emoji-data.mjs` builds that list and the picker data from `emojibase-data` (MIT, a dev dependency). Both files are committed. Newer emoji show as empty boxes on many devices, so they wait.
3. An item takes 20 different emoji at most (`Emoji::MAX_PER_ITEM`). A 21st emoji is refused with 422, and adding to an existing emoji always works.
4. The summary and "who reacted" list the emoji in the order of their first reaction, so a new emoji comes last.
5. The picker is our own component (`Components/Reactions/EmojiPicker.tsx`). The focus stays in the search field, the arrow keys move in an 8-column grid (`aria-activedescendant`), Enter picks and Escape closes and gives the focus back. The quick reactions come first, then "Frequently used", then the Unicode groups with jump buttons, then a skin tone choice. Its data (40 kB gzip) loads in its own chunk when the picker opens.
6. "Frequently used" comes from the member's own reactions (`GET /api/v1/user/frequent-emoji`, ability `user:read`). There is no new table. The skin tone choice stays in the browser (localStorage).

## Consequences

1. The same `ReactionBar` serves posts, comments, chat messages and direct messages.
2. Updating the emoji set means: update `emojibase-data`, run the script, commit both files, and raise `MAX_VERSION` when the systems show the newer emoji.
3. Custom community emoji (uploaded images) are not covered. They would need their own table and storage, and stay an idea of #1204.

## Alternatives considered

1. A third-party picker (emoji-mart and others): it injects styles (CSP), it weighs more, and its keyboard model is not ours.
2. Accept any string that matches `\p{Extended_Pictographic}`: it lets through sequences that no system draws and text tricks (joiners, variation selectors). An allow-list is exact.
3. A table of recent emoji per member: the reactions table already holds that history.
