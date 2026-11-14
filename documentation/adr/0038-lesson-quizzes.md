# 0038. A lesson has at most one quiz, graded by the server, with one result per member

- Status: Accepted
- Date: 2026-11-14
- Issue: #1335
- Pull request: #1363

## Context

Course spaces hold lessons with text, a video ([ADR 0029](0029-course-spaces.md)) and materials ([ADR 0037](0037-course-materials.md)). Members mark lessons as done ([ADR 0034](0034-course-progress-per-member.md)). A course also needs a way for a member to check what they learned: a quiz.

Three things shape the design. The correct answers must not be readable in the browser before the member answers. The answers and the scores of a member are personal data. And courses are self-paced: nothing forces a member through the lessons in order.

## Decision

1. A quiz belongs to a lesson: 0 or 1 per lesson (`quizzes.page_id` is unique), shown after the text, the video and the materials. The order of the course stays one list of lessons. Sections and the pages of a page space have no quiz (422).
2. Question kinds: `single` (exactly one correct choice) and `multiple` (one or more correct choices; the member needs all of them and no other for the point). A question has a prompt in Markdown and an optional explanation in Markdown, rendered on the server ([ADR 0013](0013-markdown-rendered-on-the-server.md)). 1 to 50 questions per quiz, 2 to 8 choices per question. Questions hold text only: an uploaded image is kept only while a post, a comment, a message or a page uses it, so images go in the text of the lesson.
3. Tables: `quizzes` (`community_id`, `page_id`, `pass_mark`), `quiz_questions` (`quiz_id`, `position`, `kind`, `prompt`, `prompt_html`, `explanation`, `explanation_html`), `quiz_choices` (`quiz_question_id`, `position`, `label`, `is_correct`), `quiz_results` (`user_id`, `quiz_id`, `community_id`, `best_score`, `last_score`, `last_answers`, `attempts`, `passed_at`). Every foreign key cascades: a quiz goes with its lesson, its space and its community; a result also goes with the account.
4. The pass mark is a percent from 0 to 100 (70 by default). A pass mark of 0 makes the quiz a self-check: every attempt passes.
5. The server grades. The member view of a quiz has the rendered prompts and the labels of the choices only. `is_correct`, the Markdown sources and the explanations are serialized for the managers of the course only (`QuizResource::withAnswers()`, and `is_correct` is a hidden attribute of the model). A member gets the correct choices and the explanations only in the review of an attempt.
6. Results: one row per member and quiz, no history of attempts (data minimisation). It keeps the best score, the last score, the answers of the last attempt, the number of attempts and the date of the first pass. A worse attempt later does not take a pass back. Only the member reads their result: managers get no scores in this version.
7. Attempts are unlimited and rate limited (`quiz-attempts`). A member can try again at once.
8. A quiz never blocks the next lesson. A pass marks the lesson as done for the member (`TracksLessonProgress`); "Mark as done" stays, so a member can also mark the lesson by hand, and "Mark as not done" still works after a pass.
9. Who: the managers of the course write and delete a quiz (`SpacePolicy::managePages`); members of the community who can read the course answer and read their result (`SpacePolicy::trackProgress`); a reader who is not a member reads the questions of a public course and keeps no result (`SpacePolicy::view`).
10. API, in the scoped group of the pages: `GET`, `PUT` and `DELETE .../pages/{page}/quiz`, and `POST .../pages/{page}/quiz/attempts`. `PUT` creates or replaces the whole quiz in one request, so the positions stay consistent and the editor stays simple. Reading and answering need `pages:read` (an attempt changes only the member's own data, like the progress); writing needs `pages:write`. `PageResource` gets `has_quiz` for the outline.
11. When a save changes the questions or the choices, the results of the members are deleted: the old scores are about other questions. A new pass mark alone keeps the questions and the results. The lessons that members finished stay done in both cases.
12. A member who leaves the community (or is removed) loses their quiz results in it (`Community::removeUser`), like their progress.

## Consequences

1. The privacy policy and the DPA list the answers and the scores of quizzes (data rule 10).
2. A manager who fixes a typo in a question deletes the results of that quiz. The editor says so before the save.
3. Quiz changes are not broadcast: a member who has the lesson open sees a new quiz after a reload, and an attempt on a quiz that changed in between gets a validation error.
4. A lesson with a quiz can be done without a pass (by hand). The progress stays what the member says it is.
5. Later, each with its own decision: free-text questions (they need a reviewer), a summary for managers ("12 members passed"), images in questions, a limit on attempts, certificates.

## Alternatives considered

1. A quiz as its own row kind in the course list (`pages.kind = quiz`): the outline would mix lessons and quizzes, and the progress would need a second rule. Rejected: a quiz checks one lesson.
2. Grading in the browser with the answers in the page: no server work, but any member reads the answers in the page source.
3. A history of every attempt: useful for statistics, but it is more personal data than the feature needs.
4. One endpoint per question and per choice: many requests for one edit, and positions that can drift between two requests.
