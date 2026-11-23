import { z } from 'zod';
import { eventAnswerValueSchema } from './event-answer.schema';
import { pageVideoSchema } from './page.schema';
import { authorSchema } from './post.schema';
import { uploadSchema } from './upload.schema';

/**
 * The state of an event (#1513, ADR 0053). "Past" is not a state: `is_past` says it, from the end.
 * WARNING: aligned with KnowiiEventState.php
 */
export const EVENT_STATES = ['draft', 'published', 'cancelled'] as const;
export const eventStateSchema = z.enum(EVENT_STATES);
export type EventState = z.infer<typeof eventStateSchema>;

/**
 * Who gets the meeting address and the place. WARNING: aligned with KnowiiEventLocationVisibility.php
 */
export const EVENT_LOCATION_VISIBILITIES = ['members', 'going'] as const;
export const eventLocationVisibilitySchema = z.enum(EVENT_LOCATION_VISIBILITIES);
export type EventLocationVisibility = z.infer<typeof eventLocationVisibilitySchema>;

/**
 * An event of an events space (#1513, ADR 0053). The start and the end are UTC instants; `time_zone` is the zone in which
 * the host entered them. The server cuts the meeting address and the place for the readers who may not see them: they are
 * null and `can_view_location` is false. Lists carry no description: get one event for its Markdown and its HTML.
 *
 * The answers (#1515): `my_answer` is the answer of the reader and of nobody else, and `places_left` is the number of
 * places that are left when the event has a limit. `capacity`, the limit itself, is only for the people who read the
 * answers (the host, the owner and the admins). A visitor and a person who is not a member get null, false, null and null.
 *
 * After the event (#1516): `replay_url` and `replay` (the player of one of the two video hosts of lessons, else null) are
 * for the members who can see the event (`can_view_replay`): a visitor and a person who is not a member get false, null
 * and null, and nothing that says whether a replay exists. `notes` (Markdown) and `notes_html` are for every reader of the
 * event, with the description: lists carry none.
 * WARNING: aligned with EventResource.php
 */
export const eventSchema = z.object({
  cuid: z.string(),
  title: z.string(),
  slug: z.string(),
  state: eventStateSchema,
  starts_at: z.coerce.date(),
  ends_at: z.coerce.date(),
  time_zone: z.string(),
  is_past: z.boolean(),
  location_visibility: eventLocationVisibilitySchema,
  can_view_location: z.boolean(),
  meeting_url: z.string().nullable(),
  place: z.string().nullable(),
  // The answer of the reader. Null: no answer, a visitor, a person who is not a member
  my_answer: eventAnswerValueSchema.nullable(),
  // A member who can see the event, while it is published and not over
  can_answer: z.boolean(),
  // The host says that a replay will exist: "Replay only" is an answer
  replay_expected: z.boolean(),
  // From 0, for a member who can see an event with a limit. Null: no limit, or not a member
  places_left: z.number().int().nonnegative().nullable(),
  // The limit of places, for the host, the owner and the admins. Null for the others, and when there is no limit
  capacity: z.number().int().positive().nullable(),
  cover: uploadSchema.nullable(),
  // Empty: a decorative image
  cover_alt: z.string().nullable(),
  // Null when the account of the host was deleted ("Deleted member")
  host: authorSchema.nullable(),
  space: z.object({ cuid: z.string(), slug: z.string(), name: z.string() }).optional(),
  // The community of the event, when the read gives it (#1515): "My events" names it on its rows
  community: z.object({ cuid: z.string(), slug: z.string(), name: z.string() }).optional(),
  body: z.string().nullable(),
  body_html: z.string().nullable(),
  // After the event (#1516): the server cuts the replay for a visitor and a person who is not a member
  can_view_replay: z.boolean(),
  replay_url: z.string().nullable(),
  // The click-to-load player of a lesson, for a link of one of its two video hosts. Null: another address, or no replay
  replay: pageVideoSchema.nullable(),
  // With the description only. Empty: no notes
  notes: z.string().nullable(),
  notes_html: z.string().nullable(),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});
// Not `Event`: the name of the DOM events
export type SpaceEvent = z.infer<typeof eventSchema>;

/**
 * What the reader may do on the page of an event (#1514). All false for a visitor and for a person who is not a member.
 * WARNING: aligned with EventController::show
 */
export interface EventPermissions {
  // Edit, publish, publish again, back to draft: the owner, the admins and the host
  canUpdate: boolean;
  // Those people, and the moderators of the space
  canCancel: boolean;
  canDelete: boolean;
  // The right to change this event, and the right to create one in the space
  canDuplicate: boolean;
  // Read who answered Yes and Replay only (#1515): the owner, the admins and the host, never for a draft, never a moderator
  canViewAnswers: boolean;
  // Comment the event (#1516): a member who can see it, never on a draft
  canComment: boolean;
  // Delete the comments of others and their replies: the people who moderate the space
  canModerate: boolean;
  // A delete would be refused (#1516): the event is published, not over, and members answered Yes or Replay only. "Delete"
  // offers to cancel it first
  mustCancelBeforeDelete: boolean;
}

/**
 * What the reader may do in the form of an event (#1514). WARNING: aligned with EventController::form
 */
export interface EventFormPermissions {
  // "Publish now": tells the members without the 5 minutes of waiting
  canNotifyNow: boolean;
  // The owner and the admins see and change the events of every host
  canManageAllEvents: boolean;
}
