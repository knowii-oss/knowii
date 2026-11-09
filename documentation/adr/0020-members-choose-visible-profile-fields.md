# 0020. Members choose which profile fields the other members see

- Status: Accepted
- Date: 2026-11-09
- Issue: #1076

## Context

A member page shows the bio, the location and the links of a member to the other members of the communities that they share (#1177). The phone was removed from it (#1234). Members could not choose: a link or a location that a member wants to keep for themselves was shown to every community they join.

## Decision

1. Each profile field that other members can see has a switch: bio, location, phone and each link (`ProfileFieldVisibility::fields()`). The email address is never shown.
2. The choices are stored on the profile (`user_profiles.field_visibility`, JSON). A field without a choice takes its default: shown, except the phone, which stays hidden until the member shows it. This keeps what members saw before.
3. "Shown" means: to the members of the communities that the member belongs to. There is no per-community choice.
4. `MemberProfileResource` applies the choices: a hidden field is empty, a hidden link is left out. Only the member sees all their choices (`UserProfileResource`).
5. The profile form has "Visible to the members of my communities" under each field. A form sent without the choices keeps them.

## Consequences

1. A new profile field that others can see must be added to `ProfileFieldVisibility::fields()` and to `PROFILE_VISIBILITY_FIELDS` (user.schema.ts), with its default.
2. Public profiles (visitors, M2 public spaces) need their own decision: these switches only cover members.

## Alternatives considered

1. One switch for the whole profile: too coarse; members want to show a website and hide a location.
2. A choice per community: more control, but a matrix of fields and communities that few members would manage. Can come later on top of this one.
