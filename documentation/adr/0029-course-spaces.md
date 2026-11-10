# 0029. Lessons are pages, sections are rows of the same list

- Status: Accepted
- Date: 2026-11-10
- Issue: #1188
- Pull request: #1305

## Context

Course spaces hold the Knowledge Management for Beginners and Obsidian Starter Course courses: ordered lessons with videos, grouped in sections for long courses. Page spaces (#1179) already have ordered Markdown pages with images, an editor, the order buttons, previous and next links, the API and live updates.

Videos come from YouTube or Vimeo. The Content Security Policy must not open `frame-src` to a whole scheme (#1183), and members' browsers must not call a provider before the member asks for the video.

## Decision

1. A lesson is a page (`pages`) of a course space, with an optional `video_url`. `KnowiiSpaceType::holdsPages()` covers page spaces and course spaces: the policy, the API, the space page and the page view serve both.
2. A section is a row of the same ordered list, of kind `section` (`pages.kind`): a title only, no text, no video (database check `pages_section_without_video`). It is the heading of the rows after it, up to the next section. It has no page of its own; the previous and next links and the lesson numbers skip it. The order buttons and the order API move sections and lessons alike, so a lesson changes section by moving past a heading. Page spaces can use sections too.
3. Videos go through an allow-list (`App\Support\VideoEmbed`): YouTube addresses become `https://www.youtube-nocookie.com/embed/{id}`, Vimeo addresses `https://player.vimeo.com/video/{id}?dnt=1`. Any other address is refused (400). `frame-src` and the Permissions-Policy features that players need (autoplay, encrypted-media, fullscreen, picture-in-picture) add exactly these two origins.
4. The lesson shows a Play button first. The player frame loads only when the member presses it: before, no request goes to the provider. YouTube starts at once; the Vimeo player shows its own Play button (it refuses autoplay in some browsers).

## Consequences

1. Code that lists the pages of a space must handle sections: no link, no number.
2. Video embeds in posts and comments (#1206) reuse `VideoEmbed` and the same player.
3. Progress per member (#1304) and courses shown as locked (#869) come later; access to a course follows the space visibility and access groups (ADR 0025).

## Alternatives considered

1. A `page_sections` table with lessons inside sections: a two-level order, a second set of reorder endpoints and screens, and empty sections handled apart. The flat list keeps one order.
2. Lessons as a separate model: the page editor, images, ordering, API and live updates built a second time.
3. Embedding the players without the Play button: every visit of a lesson would call YouTube or Vimeo, with their cookies or tracking, before the member chose to watch.
