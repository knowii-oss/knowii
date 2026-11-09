# 0021. Avatars are square images stored by Knowii, on the user

- Status: Accepted
- Date: 2026-11-09
- Issue: #109

## Context

Members want a picture next to their name (#109). Jetstream's profile photos were off. When on, they store the uploaded file as it is (metadata such as the GPS location included) and fall back to a third-party placeholder service, which the strict CSP ([ADR 0007](0007-strict-content-security-policy.md)) and the privacy of members forbid. The Fortify profile update still accepted a `photo` field and stored it without any check of its content.

Every list of authors and members needs the picture. The path lived on `user_profiles`, so each list would load the profiles too.

## Decision

1. An avatar is uploaded through the API (`POST /api/v1/user/avatar`, multipart, the second upload endpoint after #1178) and removed with `DELETE`.
2. `ImageProcessor` checks it (JPEG, PNG, WebP or GIF, 10 MB, pixel limit), crops the centered square, scales it to 256 pixels and re-encodes it to WebP without metadata.
3. The file is stored on the uploads disk at `avatars/{user cuid}/{new cuid}.webp`: a new name for each avatar, so browsers never show a stale one. The previous file is deleted.
4. The path is stored on the user (`users.avatar_path`), so every resource built on a user (authors, members, the current user) gives `avatar_url` without another query. The path itself is never serialized.
5. Without an avatar, `avatar_url` is null and the interface shows the initials. No third-party placeholder.
6. The Jetstream photo code goes away: the `HasProfilePhoto` trait, the `photo` field of the profile update, and `user_profiles.profile_photo_path`.

## Consequences

1. The CSP needs no change: avatars come from the storage origin, like the images of posts.
2. Deleting an account must delete its `avatars/{user cuid}/` folder (with the uploads clean-up, #1201).
3. Avatars are shown to the members who see the name. They are not part of the field choices of [ADR 0020](0020-members-choose-visible-profile-fields.md).

## Alternatives considered

1. Jetstream profile photos: raw files, third-party placeholder, path on another table.
2. Keep the path on the profile: one more relation to load in every list of authors.
3. Scale without cropping: avatars of different shapes in a round frame.
