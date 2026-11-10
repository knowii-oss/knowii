# 0034. Course progress is one row per lesson done, private to the member

- Status: Accepted
- Date: 2026-11-10
- Issue: #1304
- Pull request: #1337

## Context

Course spaces (ADR 0029) hold ordered lessons, but a member cannot see which lessons they finished or where to continue. The outline needs "3 of 12 lessons done", a check per lesson and a "Continue" button; the lesson page needs "Mark as done".

The progress is personal data about the member, in a community. It must go with the account (ADR 0024), and nobody else reads it.

## Decision

1. Table `lesson_progress`: one row per member and lesson done (`user_id`, `community_id`, `space_id`, `page_id`, `completed_at`), unique on `(user_id, page_id)`. Like `chat_reads`, the rows are never exposed one by one, so they have no cuid. Every foreign key cascades: the row goes with the account, the lesson, the space and the community.
2. A member who leaves the community (or is removed) loses their progress in it (`Community::removeUser`), like their access groups.
3. Only lessons of course spaces have a progress. A section, or a page of a page space, gives a 422.
4. `SpacePolicy::trackProgress`: members of the community who can read the space. A signed-in outsider reads a public course without a progress (403 on the API, no progress in the pages).
5. API: `GET .../spaces/{space}/progress`, `PUT` and `DELETE .../pages/{page}/progress`, idempotent. They need `pages:read` only: they change the member's own data. Each answers with the progress of the course (`App\Support\CourseProgress`): `total`, `completed`, `completed_lessons`, `next_lesson` (the first lesson in the order that is not done).
6. The lesson page has "Mark as done" and "Mark as not done". Going to the next lesson with its link marks the current lesson as done first. The outline shows a check per lesson (with a text alternative), the count, and "Start the course", "Continue" or "Review the course".

## Consequences

1. A lesson added later to a course counts in `total` and is not done: a member who finished the course can see "Continue" again.
2. The privacy policy lists the lessons marked as done (data rule 10).
3. No certificates, no completion events for other members, no statistics for the managers: these need their own decisions.
4. Quizzes (#1335) can later mark a lesson as done when the member passes.

## Alternatives considered

1. A JSON list of done lessons per member and course: no foreign keys, so deleting a lesson leaves stale cuids and the counts drift.
2. Mark lessons as done when the member opens them: opening is not finishing, and the member could not undo it.
