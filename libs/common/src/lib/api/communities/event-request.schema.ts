import { z } from 'zod';
import {
  MAX_EVENT_CAPACITY,
  MAX_LENGTH_EVENT_BODY,
  MAX_LENGTH_EVENT_COVER_ALT,
  MAX_LENGTH_EVENT_MEETING_URL,
  MAX_LENGTH_EVENT_NOTES,
  MAX_LENGTH_EVENT_PLACE,
  MAX_LENGTH_EVENT_REPLAY_URL,
  MAX_LENGTH_EVENT_TITLE,
  MIN_EVENT_CAPACITY,
  MIN_LENGTH_EVENT_TITLE,
} from '../../constants';
import { eventLocationVisibilitySchema } from '../../types/event.schema';
import { safeHttpUrl } from '../../utils/safe-url.fn';

/**
 * The scope of a list of events of a space. WARNING: aligned with EventApiController.php and SpaceController.php
 */
export const EVENT_SCOPES = ['upcoming', 'past'] as const;
export const eventScopeSchema = z.enum(EVENT_SCOPES);
export type EventScope = z.infer<typeof eventScopeSchema>;

/**
 * A date of a native `date` field ("2026-11-18") and a time of a native `time` field ("17:00")
 */
const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;
const TIME_PATTERN = /^\d{2}:\d{2}$/;

/**
 * A start or an end as the API takes it: ISO 8601 with the offset of the zone ("2026-11-18T17:00:00+01:00").
 * WARNING: aligned with EventTimes::PATTERN (the server also checks the offset against the zone)
 */
export const EVENT_TIME_PATTERN = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:\d{2})$/;

const eventTimeSchema = z.string().regex(EVENT_TIME_PATTERN, { error: 'Use a date and a time with an offset' });

const titleSchema = z
  .string()
  .trim()
  .min(MIN_LENGTH_EVENT_TITLE, { error: `Use between ${MIN_LENGTH_EVENT_TITLE} and ${MAX_LENGTH_EVENT_TITLE} characters` })
  .max(MAX_LENGTH_EVENT_TITLE, { error: `The title is too long (maximum ${MAX_LENGTH_EVENT_TITLE} characters)` });
const bodySchema = z
  .string()
  .max(MAX_LENGTH_EVENT_BODY, { error: `The description is too long (maximum ${MAX_LENGTH_EVENT_BODY} characters)` });
const coverAltSchema = z
  .string()
  .trim()
  .max(MAX_LENGTH_EVENT_COVER_ALT, {
    error: `The description of the cover is too long (maximum ${MAX_LENGTH_EVENT_COVER_ALT} characters)`,
  });
const placeSchema = z
  .string()
  .trim()
  .max(MAX_LENGTH_EVENT_PLACE, { error: `The place is too long (maximum ${MAX_LENGTH_EVENT_PLACE} characters)` });
// Empty, or an absolute http or https address: the page shows it as a link (rules.md, Front-end 5)
const meetingUrlSchema = z
  .string()
  .trim()
  .max(MAX_LENGTH_EVENT_MEETING_URL, { error: `The address is too long (maximum ${MAX_LENGTH_EVENT_MEETING_URL} characters)` })
  .refine((value) => value === '' || safeHttpUrl(value) !== null, { error: 'Use an address that starts with http:// or https://' });
// After the event (#1516): empty, or an absolute http or https address. The page shows the player of a lesson for a link
// of its two video hosts, and a plain link for any other address
const replayUrlSchema = z
  .string()
  .trim()
  .max(MAX_LENGTH_EVENT_REPLAY_URL, { error: `The address is too long (maximum ${MAX_LENGTH_EVENT_REPLAY_URL} characters)` })
  .refine((value) => value === '' || safeHttpUrl(value) !== null, { error: 'Use an address that starts with http:// or https://' });
const notesSchema = z
  .string()
  .max(MAX_LENGTH_EVENT_NOTES, { error: `The notes are too long (maximum ${MAX_LENGTH_EVENT_NOTES} characters)` });

/**
 * What a wrong limit of places says, next to its field
 */
export const EVENT_CAPACITY_ERROR = `Use a whole number from ${MIN_EVENT_CAPACITY} to ${MAX_EVENT_CAPACITY}, or leave the field empty`;

/**
 * The limit of places as the host types it (#1515): an empty text (no limit), or a whole number from 1 to 5 000, in
 * digits only ("1.5", "1e2" and "abc" are refused)
 */
const capacityTextSchema = z
  .string()
  .trim()
  .refine(
    (value) => {
      if (value === '') {
        return true;
      }
      const limit = Number(value);
      return /^\d+$/.test(value) && limit >= MIN_EVENT_CAPACITY && limit <= MAX_EVENT_CAPACITY;
    },
    { error: EVENT_CAPACITY_ERROR },
  );

/**
 * The limit of places of a request: the number of the text that the host typed, or null for an empty text (no limit)
 */
export function eventCapacityOf(text: string): number | null {
  const value = text.trim();
  return value === '' ? null : Number(value);
}

/**
 * What the host types in the form of an event (#1514, design 4.2 and 6.3): the texts, and the start and the end as the
 * native date and time fields give them, in the chosen zone. The form turns the dates and the times into the instants of
 * `eventRequestSchema` (`zonedDateTimeToIso`). `end_date` is empty while the end is on the day of the start. The limit
 * of places is a text: empty means no limit (#1515).
 * WARNING: aligned with CreateEvent::rules()
 */
export const eventFormSchema = z.object({
  title: titleSchema,
  body: bodySchema,
  cover_alt: coverAltSchema,
  start_date: z.string().regex(DATE_PATTERN, { error: 'Choose a start date' }),
  start_time: z.string().regex(TIME_PATTERN, { error: 'Choose a start time' }),
  end_date: z.string().refine((value) => value === '' || DATE_PATTERN.test(value), { error: 'Choose an end date' }),
  end_time: z.string().regex(TIME_PATTERN, { error: 'Choose an end time' }),
  time_zone: z.string().min(1, { error: 'Choose a time zone' }),
  meeting_url: meetingUrlSchema,
  place: placeSchema,
  // Who sees the meeting address and the place: every member, or the members who answer Yes
  location_visibility: eventLocationVisibilitySchema,
  capacity: capacityTextSchema,
  // "A replay will exist": "Replay only" is then an answer
  replay_expected: z.boolean(),
  // After the event (#1516): the form of a published event that ended shows them
  replay_url: replayUrlSchema,
  notes: notesSchema,
});
export type EventForm = z.infer<typeof eventFormSchema>;

/**
 * What the API takes to create an event (#1513, ADR 0053). The start and the end carry the offset of the zone; the
 * server checks it. `location_visibility`, `capacity` and `replay_expected` (#1515) are optional: a new event without
 * them gets `members`, no limit and no replay, and a change keeps the stored values.
 * WARNING: aligned with CreateEvent.php
 */
export const eventRequestSchema = z.object({
  title: titleSchema,
  body: bodySchema,
  starts_at: eventTimeSchema,
  ends_at: eventTimeSchema,
  time_zone: z.string().min(1),
  // Null or empty removes the value
  meeting_url: meetingUrlSchema.nullable(),
  place: placeSchema.nullable(),
  cover_upload_cuid: z.string().nullable(),
  // Only what the host typed. Empty: a decorative image
  cover_alt: coverAltSchema.nullable(),
  // Absent: the person who creates the event
  host_cuid: z.string().optional(),
  // Absent: a draft
  state: z.enum(['draft', 'published']).optional(),
  // With `published`: tells the members now. For the people who can moderate the space
  notify_now: z.boolean().optional(),
  // `going`: the meeting address and the place are for the members who answer Yes, the host, the owner and the admins
  location_visibility: eventLocationVisibilitySchema.optional(),
  // The limit of places, which counts the Yes answers. Null: no limit
  capacity: z.number().int().min(MIN_EVENT_CAPACITY).max(MAX_EVENT_CAPACITY).nullable().optional(),
  replay_expected: z.boolean().optional(),
});
export type EventRequest = z.infer<typeof eventRequestSchema>;

/**
 * What the API takes to change an event: every field of a create is optional, and the state has its own call
 * (`setState`). The replay link and the notes (#1516) are for a change only: a value that is not empty needs a published
 * event that ended, and null or an empty text clears it. WARNING: aligned with UpdateEvent.php
 */
export const eventUpdateRequestSchema = eventRequestSchema.omit({ state: true, notify_now: true }).partial().extend({
  replay_url: replayUrlSchema.nullable().optional(),
  notes: notesSchema.nullable().optional(),
});
export type EventUpdateRequest = z.infer<typeof eventUpdateRequestSchema>;
