# 0037. Course materials are private files and links, added by the managers of the course

- Status: Accepted
- Date: 2026-11-14
- Issue: #1336
- Pull request: #1361

## Context

Course spaces hold lessons with text and a video link ([ADR 0029](0029-course-spaces.md)). A course also needs supplementary materials: a PDF, a template, a cheat sheet, for the whole course or for one part of it (a section or a lesson).

Until now the only files that Knowii stores are images: re-encoded by the server and served from a public address ([ADR 0016](0016-resource-images-stored-by-knowii.md), uploads). A document cannot be re-encoded, and the materials of a course are for the people who can read the course, which can be a paid group ([ADR 0025](0025-access-groups.md)).

## Decision

1. A material belongs to a course space, and to nothing else (`page_id` null), to a section or to a lesson. One table, `course_materials`, with a nullable foreign key to `pages` (sections and lessons are both pages, ADR 0029). A material is a file or a link, never both: a check constraint keeps it so ([ADR 0014](0014-explicit-foreign-keys-instead-of-polymorphic-relations.md) style).
2. Only the managers of the course add, rename, reorder and delete materials (`SpacePolicy::managePages`). Members who can read the space (`SpacePolicy::view`) list and download them.
3. Files are on a private disk: `COURSE_MATERIALS_DISK`, `local` by default (under `storage/app`, which the web server never serves), or an S3-compatible disk, where they are stored with a private visibility. A material has no public address. The API never returns the place of a file.
4. A download goes through `GET .../materials/{material}/download`, which checks the access each time. From a local disk the server streams the file. From a storage service it redirects to a temporary signed address (5 minutes).
5. A file never renders inside the Knowii origin: `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`, always.
6. Accepted files are documents: PDF, ZIP, EPUB, Markdown, text, CSV, JSON, Office and OpenDocument files, PNG, JPEG, WebP. The server reads the type from the content of the file and also checks the extension. No HTML, no SVG, no scripts, no video and no audio (lesson videos: #1334). 50 MB per file by default (`COURSE_MATERIALS_MAX_MB`), 20 materials per course, section or lesson.
7. A link is an http or https address. The server never fetches it (no capture), and the client shows it through `safeHttpUrl()`.
8. Knowii keeps no record of who downloaded what. A material stores no author. So this adds no personal data, and the privacy policy does not change.
9. The files go with their rows (data rule 8): deleting a material, a lesson, a section, the course space or the community deletes the files after the commit (`App\Support\ContentFiles`).

## Consequences

1. A copied download address works only for a signed-in user who can read the course, or for 5 minutes on a storage service.
2. The backups of an instance must include the materials disk, like the uploads.
3. A large file passes through PHP on a local disk. An instance with many large materials uses a storage service.
4. Changes of materials are not broadcast: another open page shows them after a reload.
5. `SELF_HOSTING.md` and `.env.example` describe `COURSE_MATERIALS_DISK` and `COURSE_MATERIALS_MAX_MB`.

## Alternatives considered

1. Reuse the uploads of members (public addresses): anyone with the address reads a paid material.
2. Let members attach files too: it needs a quota per member, moderation and a virus scan. Later, with its own decision.
3. A virus scan (ClamAV) now: only managers upload, the types are documents, and the files are always downloaded, never run by Knowii. To add with member uploads.
4. Count the downloads per member: new personal data for little value.
