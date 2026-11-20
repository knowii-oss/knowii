import { useMemo, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import type {
  ApiResult,
  Author,
  Community,
  EventForm as EventFormValues,
  EventFormPermissions,
  EventRequest,
  EventUpdateRequest,
  Space,
  SpaceEvent,
  ZonedDateTime,
} from '@knowii/common';
import {
  ACCEPTED_IMAGE_TYPES,
  browserTimeZone,
  EVENT_URL,
  eventFormSchema,
  formatEventRange,
  instantToZonedDateTime,
  knowiiApiClient,
  MAX_EVENT_DURATION_HOURS,
  MAX_LENGTH_EVENT_COVER_ALT,
  MAX_LENGTH_EVENT_PLACE,
  MAX_LENGTH_EVENT_TITLE,
  MAX_UPLOAD_SIZE_BYTES,
  POST_EDITOR_ROWS,
  readsTheSameInZones,
  SPACE_URL,
  timeZoneOptions,
  useAppData,
  utcOffsetLabel,
  zonedDateTimeToIso,
} from '@knowii/common';
import { FaImage, FaTrash } from 'react-icons/fa6';
import { MemberPicker } from '@/Components/Messages/MemberPicker';
import { useImageUploader } from '@/Components/Uploads/use-image-uploader';
import {
  Button,
  buttonClasses,
  Checkbox,
  Combobox,
  CoverImage,
  InputError,
  InputLabel,
  MarkdownEditor,
  Radio,
  TextInput,
  useAccountTimeZone,
  useFocusAfterRender,
} from '@/Components/ui';
import { EVENT_WORDS } from './event-words';
import { useAfterPress } from './use-after-press';

export interface EventFormProps {
  community: Community;
  space: Space;
  /**
   * The event to change, with its description. Null: a new event
   */
  event: SpaceEvent | null;
  /**
   * The zones that the server accepts
   */
  timeZones: readonly string[];
  permissions: EventFormPermissions;
  /**
   * The reader: the host of a new event, unless they name another member
   */
  currentUser: Author;
}

/**
 * What the person wants from a save
 */
type Intent = 'draft' | 'publish' | 'save';

/**
 * The five fields of "When", as they were when the person left a field: the sentence, the alerts and the questions
 * about the clocks follow this copy, never each key (design 11.1 point 3)
 */
type WhenValues = Pick<EventFormValues, 'start_date' | 'start_time' | 'end_date' | 'end_time' | 'time_zone'>;

/**
 * The answer to "summer time or winter time?", for one date, time and zone
 */
interface TwiceChoice {
  key: string;
  value: 'summer' | 'winter';
}

const whenOf = (values: EventFormValues): WhenValues => ({
  start_date: values.start_date,
  start_time: values.start_time,
  end_date: values.end_date,
  end_time: values.end_time,
  time_zone: values.time_zone,
});

/**
 * The day after a date of a date field: "2026-11-18" gives "2026-11-19". An empty text for a text that is no date.
 */
function nextDay(date: string): string {
  const next = new Date(`${date}T00:00:00Z`);
  if (Number.isNaN(next.getTime())) {
    return '';
  }
  next.setUTCDate(next.getUTCDate() + 1);
  return next.toISOString().slice(0, 10);
}

/**
 * The end is on another day than the start when its time is not after the start time: an event lasts 24 hours at most
 */
function endsOnAnotherDay(startTime: string, endTime: string): boolean {
  return startTime !== '' && endTime !== '' && endTime <= startTime;
}

const choiceKey = (date: string, time: string, zone: string) => `${date}T${time}@${zone}`;

/**
 * The instant of a local time: itself, or for a time that exists twice the one that the person chose (null until then)
 */
function instantOf(time: ZonedDateTime, choice: TwiceChoice | null, key: string): { iso: string; offsetMinutes: number } | null {
  if (time.kind === 'instant') {
    return { iso: time.iso, offsetMinutes: time.offsetMinutes };
  }
  if (time.kind === 'twice' && choice?.key === key) {
    return time[choice.value];
  }
  return null;
}

/**
 * The start values of the form: the event in its own zone, or an empty form in the zone of the reader
 */
function startValues(event: SpaceEvent | null, defaultZone: string): EventFormValues {
  const start = event ? instantToZonedDateTime(event.starts_at, event.time_zone) : null;
  const end = event ? instantToZonedDateTime(event.ends_at, event.time_zone) : null;
  return {
    title: event?.title ?? '',
    body: event?.body ?? '',
    cover_alt: event?.cover_alt ?? '',
    start_date: start?.date ?? '',
    start_time: start?.time ?? '',
    end_date: start && end && end.date !== start.date ? end.date : '',
    end_time: end?.time ?? '',
    time_zone: event?.time_zone ?? defaultZone,
    meeting_url: event?.meeting_url ?? '',
    place: event?.place ?? '',
  };
}

/**
 * The choice that the stored instant of an event means, when its local time exists twice
 */
function storedChoice(date: string, time: string, zone: string, offsetMinutes: number | undefined): TwiceChoice | null {
  const local = zonedDateTimeToIso(date, time, zone);
  if (local.kind !== 'twice' || offsetMinutes === undefined) {
    return null;
  }
  return { key: choiceKey(date, time, zone), value: local.summer.offsetMinutes === offsetMinutes ? 'summer' : 'winter' };
}

/**
 * The form of the host of an event (#1514, ADR 0053, design 4.2, 6.3 and 11.1): the title, the description, the cover,
 * "When" (native date and time fields, and the zone in a list that filters), the meeting address, the place and the
 * host. It is a page, not a dialog. Every save goes through the API.
 *
 * The times. The host types a date and a time in a zone; the form sends the instant with the offset of the zone at
 * that time, and the zone. A status under the fields repeats what was typed, in words, with the zone and its offset,
 * and what it is in the zone of the reader: it is the check against a wrong zone. It changes when the person leaves a
 * field, never at each key. A time that the zone skips shows an alert with the two times around the gap, and nothing
 * is sent. A time that exists twice asks "summer time or winter time?".
 *
 * The publication of a draft takes two requests (the changes, then the state), and three when a host who is not the
 * owner or an admin names another host (the new host comes last: the publication needs the rights of the host). The
 * form follows the event that the server answered after each one: when a later request fails, its buttons are the ones
 * of the state that the event really has, and the message says what was saved.
 *
 * No field for who sees the meeting address: until members can answer, the other value would hide the address from
 * every member. The form never sends that field, so a change keeps the stored value.
 */
export function EventForm({ community, space, event, timeZones, permissions, currentUser }: EventFormProps) {
  const { showToast } = useAppData();
  const accountZone = useAccountTimeZone();
  const browserZone = useMemo(() => browserTimeZone(), []);
  const readerZone = accountZone ?? browserZone ?? 'UTC';
  const focusAfterRender = useFocusAfterRender();
  const afterPress = useAfterPress();
  const images = useImageUploader(community.cuid);
  const renderPreview = useMemo(
    () => (markdown: string, signal: AbortSignal) => knowiiApiClient.markdown.preview(markdown, signal, community.cuid),
    [community.cuid],
  );

  // The event as the server last answered it: the prop at the start, then the answer of each request of a save
  const [current, setCurrent] = useState<SpaceEvent | null>(event);
  const initial = useMemo(() => startValues(event, readerZone), [event, readerZone]);
  const form = useForm<EventFormValues>({
    resolver: zodResolver(eventFormSchema),
    defaultValues: initial,
    // The errors show after a save, next to their fields: the focus stays where the person is (design 11.1 point 3)
    shouldFocusError: false,
  });
  const errors = form.formState.errors;

  // The zones of the list: the ones that the server accepts, under the names that the browser knows
  const zoneOptions = useMemo(() => {
    const accepted = new Set(timeZones);
    const options = timeZoneOptions([...timeZones, initial.time_zone]);
    return accepted.size === 0 ? options : options.filter((option) => accepted.has(option.value) || option.value === initial.time_zone);
  }, [timeZones, initial.time_zone]);
  // At the top of the list: the zone of the account, then the zone of the browser
  const pinnedZones = useMemo(() => [accountZone, browserZone].filter((zone) => zone !== null), [accountZone, browserZone]);

  // "When", as it was when the person left a field
  const [checked, setChecked] = useState<WhenValues>(() => whenOf(initial));
  const [startChoice, setStartChoice] = useState<TwiceChoice | null>(() =>
    event
      ? storedChoice(
          initial.start_date,
          initial.start_time,
          initial.time_zone,
          instantToZonedDateTime(event.starts_at, event.time_zone)?.offsetMinutes,
        )
      : null,
  );
  const [endChoice, setEndChoice] = useState<TwiceChoice | null>(() =>
    event
      ? storedChoice(
          initial.end_date || initial.start_date,
          initial.end_time,
          initial.time_zone,
          instantToZonedDateTime(event.ends_at, event.time_zone)?.offsetMinutes,
        )
      : null,
  );
  // The answers of the stored event (when the form opened, then after each request that worked): a save compares with them
  const startChoiceAtStart = useRef(startChoice);
  const endChoiceAtStart = useRef(endChoice);
  // The questions about the clocks show their own error only after a save without an answer
  const [choiceMissing, setChoiceMissing] = useState(false);

  const [cover, setCover] = useState<{ cuid: string; url: string } | null>(
    event?.cover ? { cuid: event.cover.cuid, url: event.cover.url } : null,
  );
  const [coverBusy, setCoverBusy] = useState(false);
  const [coverError, setCoverError] = useState<string | null>(null);
  const [coverStatus, setCoverStatus] = useState('');
  const coverInput = useRef<HTMLInputElement>(null);
  const coverButton = useRef<HTMLButtonElement>(null);

  const [host, setHost] = useState<Author[]>(() => (event ? (event.host ? [event.host] : []) : [currentUser]));
  const [hostError, setHostError] = useState<string | null>(null);

  const [notifyNow, setNotifyNow] = useState(false);
  const [busy, setBusy] = useState<Intent | null>(null);
  const busyRef = useRef(false);
  const [formError, setFormError] = useState<string | null>(null);

  const isDraft = current === null || current.state === 'draft';
  const slugs = { communitySlug: community.slug, spaceSlug: space.slug };
  const cancelHref = current ? route(EVENT_URL, { ...slugs, eventSlug: current.slug }) : route(SPACE_URL, slugs);
  const chosenHost = host[0] ?? null;
  // A draft that its creator gives to another host is for that host, the owner and the admins only (ADR 0053 point 4)
  const handsOverDraft = isDraft && chosenHost !== null && chosenHost.cuid !== currentUser.cuid && !permissions.canManageAllEvents;

  /**
   * What "When" means: the two local times, the instants when they are known, and what is wrong
   */
  const resolve = (when: WhenValues, start: TwiceChoice | null, end: TwiceChoice | null) => {
    const endDate = when.end_date !== '' ? when.end_date : when.start_date;
    const startKey = choiceKey(when.start_date, when.start_time, when.time_zone);
    const endKey = choiceKey(endDate, when.end_time, when.time_zone);
    const startLocal = zonedDateTimeToIso(when.start_date, when.start_time, when.time_zone);
    const endLocal = zonedDateTimeToIso(endDate, when.end_time, when.time_zone);
    const startInstant = instantOf(startLocal, start, startKey);
    const endInstant = instantOf(endLocal, end, endKey);
    let order: string | null = null;
    if (startInstant && endInstant) {
      const length = new Date(endInstant.iso).getTime() - new Date(startInstant.iso).getTime();
      if (length <= 0) {
        order = EVENT_WORDS.endBeforeStart;
      } else if (length > MAX_EVENT_DURATION_HOURS * 3_600_000) {
        order = EVENT_WORDS.tooLong(MAX_EVENT_DURATION_HOURS);
      }
    }
    return { startLocal, endLocal, startInstant, endInstant, startKey, endKey, order };
  };

  const when = resolve(checked, startChoice, endChoice);

  // "Wednesday 18 November 2026, 17:00 to 18:00, Central European Standard Time (UTC+1). In your zone: the same."
  let summary = '';
  if (when.startInstant && when.endInstant && when.order === null) {
    const start = new Date(when.startInstant.iso);
    const end = new Date(when.endInstant.iso);
    const same = readsTheSameInZones(start, checked.time_zone, readerZone) && readsTheSameInZones(end, checked.time_zone, readerZone);
    summary = `${formatEventRange(start, end, checked.time_zone, { year: 'always', offset: true })}. ${
      same ? EVENT_WORDS.inYourZoneTheSame : EVENT_WORDS.inYourZone(formatEventRange(start, end, readerZone))
    }`;
  } else if ((when.startLocal.kind === 'twice' && !when.startInstant) || (when.endLocal.kind === 'twice' && !when.endInstant)) {
    // The question "summer time or winter time?" appeared above, before the field that has the focus: the status says so
    summary = EVENT_WORDS.twiceStatus;
  }

  // The person left a field of "When": the sentence, the alerts and the questions follow. Not in the middle of a press
  // on a button: what appears under the fields would move the button away, and the click would be lost
  const check = () => afterPress(() => setChecked(whenOf(form.getValues())));

  // An event of the API can end on the next day with an end time after its start time (24 hours over a change of the
  // clocks: 12:00 to 13:00 the next day). Its "End date" shows, and only the person changes it
  const keepsEndDate = initial.end_date !== '' && !endsOnAnotherDay(initial.start_time, initial.end_time);

  // "End date" follows the two times: the next day when the end time is not after the start time, else no field
  const syncEndDate = () => {
    const values = form.getValues();
    const anotherDay = endsOnAnotherDay(values.start_time, values.end_time);
    if (!anotherDay && keepsEndDate) {
      return;
    }
    const next = anotherDay ? nextDay(values.start_date) : '';
    if (values.end_date !== next) {
      form.setValue('end_date', next, { shouldDirty: true });
    }
  };

  const [startTime, endTime, coverAlt] = useWatch({ control: form.control, name: ['start_time', 'end_time', 'cover_alt'] });
  const endTimeNotAfterStart = endsOnAnotherDay(startTime, endTime);
  const showEndDate = endTimeNotAfterStart || keepsEndDate;

  const chooseCover = async (file: File) => {
    setCoverError(null);
    setCoverStatus('');
    if (!(ACCEPTED_IMAGE_TYPES as readonly string[]).includes(file.type)) {
      setCoverError('Choose a JPEG, PNG, WebP or GIF image.');
      return;
    }
    if (file.size > MAX_UPLOAD_SIZE_BYTES) {
      setCoverError(`The image is too large (maximum ${MAX_UPLOAD_SIZE_BYTES / (1024 * 1024)} MB).`);
      return;
    }
    setCoverBusy(true);
    // The upload needs a description. The description of the cover stays what the host typed, also when it is empty
    const description = form.getValues('cover_alt').trim() || EVENT_WORDS.coverUploadAlt;
    const result = await knowiiApiClient.communities.uploads.image(community.cuid, file, description);
    setCoverBusy(false);
    if (coverInput.current) {
      coverInput.current.value = '';
    }
    if (!result.ok) {
      setCoverError(result.message);
      return;
    }
    setCover({ cuid: result.data.cuid, url: result.data.url });
    setCoverStatus(EVENT_WORDS.coverUploaded);
  };

  const removeCover = () => {
    setCover(null);
    setCoverError(null);
    setCoverStatus(EVENT_WORDS.coverRemoved);
    // The button goes away with the cover: the focus goes to the button that chooses an image
    focusAfterRender(() => coverButton.current);
  };

  /**
   * The errors of the server next to their fields. True when one field took an error.
   */
  const showFieldErrors = (result: Extract<ApiResult<unknown>, { ok: false }>): boolean => {
    const fields: Record<string, keyof EventFormValues> = {
      title: 'title',
      body: 'body',
      cover_alt: 'cover_alt',
      // The server names the instants: the person typed them in "Start time" and "End time"
      starts_at: 'start_time',
      ends_at: 'end_time',
      time_zone: 'time_zone',
      meeting_url: 'meeting_url',
      place: 'place',
    };
    let shown = false;
    for (const [name, field] of Object.entries(fields)) {
      const message = result.errors[name]?.[0];
      if (message) {
        form.setError(field, { message });
        shown = true;
      }
    }
    const coverMessage = result.errors['cover_upload_cuid']?.[0];
    if (coverMessage) {
      setCoverError(coverMessage);
      shown = true;
    }
    const hostMessage = result.errors['host_cuid']?.[0];
    if (hostMessage) {
      setHostError(hostMessage);
      shown = true;
    }
    return shown;
  };

  /**
   * A request failed. A refused rule (422), a missing right, a failure of the network: above the buttons, with the
   * words of the server; a field error shows next to its field. `savedBefore`: what the requests before this one saved.
   */
  const fail = (result: Extract<ApiResult<unknown>, { ok: false }>, savedBefore?: string) => {
    const messages = [showFieldErrors(result) ? undefined : result.message, savedBefore].filter((message) => message !== undefined);
    setFormError(messages.length === 0 ? null : messages.join(' '));
  };

  /**
   * The event as the server answered it: the buttons, the next save and its toast follow its real state
   */
  const keep = (answer: SpaceEvent) => {
    setCurrent(answer);
    startChoiceAtStart.current = startChoice;
    endChoiceAtStart.current = endChoice;
  };

  const save = async (intent: Intent, values: EventFormValues) => {
    setFormError(null);
    setHostError(null);
    setCoverError(null);
    setChecked(whenOf(values));
    const times = resolve(whenOf(values), startChoice, endChoice);
    if (!times.startInstant || !times.endInstant || times.order !== null) {
      // A time that the zone skips, a question without an answer, an end before the start: nothing is sent
      setChoiceMissing(true);
      setFormError(EVENT_WORDS.fixTheFields);
      return;
    }
    if (chosenHost === null) {
      setHostError(EVENT_WORDS.hostMissing);
      setFormError(EVENT_WORDS.fixTheFields);
      return;
    }

    const fields = {
      title: values.title,
      body: values.body,
      starts_at: times.startInstant.iso,
      ends_at: times.endInstant.iso,
      time_zone: values.time_zone,
      meeting_url: values.meeting_url === '' ? null : values.meeting_url,
      place: values.place === '' ? null : values.place,
      cover_upload_cuid: cover?.cuid ?? null,
      cover_alt: values.cover_alt === '' ? null : values.cover_alt,
    };
    const tellNow = intent === 'publish' && permissions.canNotifyNow && notifyNow;

    let saved: SpaceEvent;
    if (current === null) {
      const request: EventRequest = {
        ...fields,
        // Absent: the person who creates the event is its host
        ...(chosenHost.cuid === currentUser.cuid ? {} : { host_cuid: chosenHost.cuid }),
        state: intent === 'publish' ? 'published' : 'draft',
        ...(tellNow ? { notify_now: true } : {}),
      };
      const created = await knowiiApiClient.communities.events.create(community.cuid, space.cuid, request);
      if (!created.ok) {
        fail(created);
        return;
      }
      saved = created.data;
    } else {
      // Only what changed: a save of an untouched form changes nothing, and never moves the times of an old event
      const before = startValues(current, readerZone);
      const stored = resolve(whenOf(before), startChoiceAtStart.current, endChoiceAtStart.current);
      const changes: EventUpdateRequest = {};
      if (fields.title !== before.title.trim()) {
        changes.title = fields.title;
      }
      if (fields.body !== before.body) {
        changes.body = fields.body;
      }
      if (
        fields.starts_at !== stored.startInstant?.iso ||
        fields.ends_at !== stored.endInstant?.iso ||
        fields.time_zone !== before.time_zone
      ) {
        changes.starts_at = fields.starts_at;
        changes.ends_at = fields.ends_at;
        changes.time_zone = fields.time_zone;
      }
      if ((fields.meeting_url ?? '') !== before.meeting_url) {
        changes.meeting_url = fields.meeting_url;
      }
      if ((fields.place ?? '') !== before.place) {
        changes.place = fields.place;
      }
      if (fields.cover_upload_cuid !== (current.cover?.cuid ?? null)) {
        changes.cover_upload_cuid = fields.cover_upload_cuid;
      }
      if ((fields.cover_alt ?? '') !== before.cover_alt) {
        changes.cover_alt = fields.cover_alt;
      }
      const hostChanged = chosenHost.cuid !== current.host?.cuid;
      // The owner and the admins change every event, whoever its host is: the new host goes with the other changes. A
      // host who names another member needs the rights of the host to publish: the new host is named after it
      const hostAfterPublication = intent === 'publish' && hostChanged && !permissions.canManageAllEvents;
      if (hostChanged && !hostAfterPublication) {
        changes.host_cuid = chosenHost.cuid;
      }

      const ids = [community.cuid, space.cuid, current.cuid] as const;
      const updated = await knowiiApiClient.communities.events.update(...ids, changes);
      if (!updated.ok) {
        fail(updated);
        return;
      }
      keep(updated.data);
      saved = updated.data;
      if (intent === 'publish') {
        const published = await knowiiApiClient.communities.events.setState(...ids, 'published', tellNow);
        if (!published.ok) {
          fail(published, EVENT_WORDS.savedNotPublished);
          return;
        }
        // From here the event is published: the form is the one of a published event
        keep(published.data);
        saved = published.data;
        if (hostAfterPublication) {
          const handed = await knowiiApiClient.communities.events.update(...ids, { host_cuid: chosenHost.cuid });
          if (!handed.ok) {
            fail(handed, EVENT_WORDS.publishedHostNotChanged);
            return;
          }
          saved = handed.data;
        }
      }
    }

    showToast({
      severity: 'success',
      summary: intent === 'publish' ? EVENT_WORDS.eventPublished : intent === 'draft' ? EVENT_WORDS.draftSaved : EVENT_WORDS.changesSaved,
    });
    // A draft that now has another host is no longer for its creator: its page would not exist for them
    router.visit(intent === 'draft' && handsOverDraft ? route(SPACE_URL, slugs) : route(EVENT_URL, { ...slugs, eventSlug: saved.slug }));
  };

  /**
   * One save at a time. A busy button keeps the focus (`Button loading`), and a second click does nothing.
   */
  const submit = (intent: Intent) =>
    form.handleSubmit(
      async (values) => {
        if (busyRef.current) {
          return;
        }
        busyRef.current = true;
        setBusy(intent);
        try {
          await save(intent, values);
        } finally {
          busyRef.current = false;
          setBusy(null);
        }
      },
      () => {
        setChecked(whenOf(form.getValues()));
        setChoiceMissing(true);
        setFormError(EVENT_WORDS.fixTheFields);
      },
    );

  const describedBy = (...ids: (string | false | undefined)[]) => ids.filter(Boolean).join(' ') || undefined;
  // "Choose 01:59 or earlier, or 03:00 or later", or "23:59 the day before" when the clocks go forward at midnight
  const skippedWords = (local: ZonedDateTime) =>
    local.kind === 'skipped'
      ? (local.beforeIsDayBefore ? EVENT_WORDS.skippedTimeDayBefore : EVENT_WORDS.skippedTime)(local.before, local.after)
      : null;
  const startSkipped = skippedWords(when.startLocal);
  const endSkipped = skippedWords(when.endLocal);
  const startTimeError = errors.start_time?.message ?? startSkipped ?? undefined;
  const endTimeError = errors.end_time?.message ?? endSkipped ?? when.order ?? undefined;

  const twiceQuestion = (
    side: 'start' | 'end',
    local: ZonedDateTime,
    time: string,
    key: string,
    choice: TwiceChoice | null,
    choose: (value: TwiceChoice) => void,
  ) => {
    if (local.kind !== 'twice') {
      return null;
    }
    const summer = utcOffsetLabel(local.summer.iso, checked.time_zone);
    const winter = utcOffsetLabel(local.winter.iso, checked.time_zone);
    const answered = choice?.key === key;
    return (
      <fieldset className="flex flex-col gap-2 rounded-lg border border-border p-3 @2xl:col-span-2">
        <legend className="px-1 text-sm font-bold">
          <span className="sr-only">{side === 'start' ? EVENT_WORDS.twiceStart : EVENT_WORDS.twiceEnd}:</span>{' '}
          {EVENT_WORDS.twiceQuestion(time, summer, winter)}
        </legend>
        {(['summer', 'winter'] as const).map((value) => (
          <label key={value} className="flex min-h-11 cursor-pointer items-center gap-3 md:min-h-0">
            <Radio
              name={`event-${side}-clock`}
              value={value}
              checked={answered && choice.value === value}
              onChange={() => choose({ key, value })}
            />
            {value === 'summer' ? EVENT_WORDS.twiceSummer(summer) : EVENT_WORDS.twiceWinter(winter)}
          </label>
        ))}
        {choiceMissing && !answered && <InputError message={EVENT_WORDS.twiceMissing} />}
      </fieldset>
    );
  };

  return (
    <form
      onSubmit={(submitEvent) => void submit(isDraft ? 'draft' : 'save')(submitEvent)}
      noValidate
      aria-labelledby="event-form-title"
      className="flex flex-col gap-6"
    >
      <div>
        <InputLabel htmlFor="event-title">{EVENT_WORDS.fieldTitle}</InputLabel>
        <TextInput
          id="event-title"
          wrapperClassName="mt-1"
          required
          maxLength={MAX_LENGTH_EVENT_TITLE}
          invalid={!!errors.title}
          aria-describedby={describedBy(errors.title && 'event-title-error')}
          {...form.register('title')}
        />
        <InputError id="event-title-error" className="mt-2" message={errors.title?.message} />
      </div>

      <div>
        <InputLabel htmlFor="event-body">{EVENT_WORDS.fieldDescription}</InputLabel>
        <div className="mt-1">
          <Controller
            name="body"
            control={form.control}
            render={({ field }) => (
              <MarkdownEditor
                id="event-body"
                value={field.value}
                onChange={field.onChange}
                onBlur={field.onBlur}
                renderPreview={renderPreview}
                rows={POST_EDITOR_ROWS}
                invalid={!!errors.body}
                images={images}
                aria-describedby={describedBy(errors.body && 'event-body-error')}
              />
            )}
          />
        </div>
        <InputError id="event-body-error" className="mt-2" message={errors.body?.message} />
      </div>

      <div role="group" aria-labelledby="event-cover-label" className="flex flex-col gap-2">
        <p id="event-cover-label" className="text-sm font-bold">
          {EVENT_WORDS.fieldCover}
        </p>
        {cover && <CoverImage src={cover.url} alt={coverAlt.trim()} className="max-w-md" />}
        <input
          ref={coverInput}
          id="event-cover-file"
          type="file"
          accept={ACCEPTED_IMAGE_TYPES.join(',')}
          className="sr-only"
          tabIndex={-1}
          aria-hidden="true"
          onChange={(changeEvent) => {
            const file = changeEvent.target.files?.[0];
            if (file) {
              void chooseCover(file);
            }
          }}
        />
        <div className="flex flex-wrap items-center gap-2">
          <Button
            ref={coverButton}
            variant="secondary"
            loading={coverBusy}
            leftIcon={<FaImage aria-hidden="true" />}
            aria-describedby={describedBy('event-cover-hint', coverError !== null && 'event-cover-error')}
            onClick={() => coverInput.current?.click()}
          >
            {cover ? EVENT_WORDS.changeCover : EVENT_WORDS.chooseCover}
          </Button>
          {cover && (
            <Button variant="ghost" disabled={coverBusy} leftIcon={<FaTrash aria-hidden="true" />} onClick={removeCover}>
              {EVENT_WORDS.removeCover}
            </Button>
          )}
        </div>
        <p id="event-cover-hint" className="text-xs text-muted">
          {EVENT_WORDS.coverFormats}
        </p>
        <p role="status" aria-live="polite" className="sr-only">
          {coverStatus}
        </p>
        <InputError id="event-cover-error" message={coverError ?? undefined} />
      </div>

      <div>
        <InputLabel htmlFor="event-cover-alt">{EVENT_WORDS.fieldCoverAlt}</InputLabel>
        <TextInput
          id="event-cover-alt"
          wrapperClassName="mt-1"
          maxLength={MAX_LENGTH_EVENT_COVER_ALT}
          invalid={!!errors.cover_alt}
          aria-describedby={describedBy('event-cover-alt-hint', errors.cover_alt && 'event-cover-alt-error')}
          {...form.register('cover_alt')}
        />
        <p id="event-cover-alt-hint" className="mt-1 text-xs text-muted">
          {EVENT_WORDS.coverAltHint}
        </p>
        <InputError id="event-cover-alt-error" className="mt-2" message={errors.cover_alt?.message} />
      </div>

      <fieldset className="flex flex-col gap-4 rounded-xl border border-border p-4">
        <legend className="px-1 text-base font-extrabold">{EVENT_WORDS.when}</legend>
        <div className="grid grid-cols-1 gap-4 @2xl:grid-cols-2">
          <div>
            <InputLabel htmlFor="event-start-date">{EVENT_WORDS.startDate}</InputLabel>
            <TextInput
              id="event-start-date"
              type="date"
              wrapperClassName="mt-1"
              required
              invalid={!!errors.start_date}
              aria-describedby={describedBy(errors.start_date && 'event-start-date-error')}
              {...form.register('start_date', { onChange: syncEndDate, onBlur: check })}
            />
            <InputError id="event-start-date-error" className="mt-2" message={errors.start_date?.message} />
          </div>
          <div>
            <InputLabel htmlFor="event-start-time">{EVENT_WORDS.startTime}</InputLabel>
            <TextInput
              id="event-start-time"
              type="time"
              wrapperClassName="mt-1"
              required
              invalid={startTimeError !== undefined}
              aria-describedby={describedBy(startTimeError !== undefined && 'event-start-time-error')}
              {...form.register('start_time', { onChange: syncEndDate, onBlur: check })}
            />
            <InputError id="event-start-time-error" className="mt-2" message={startTimeError} />
          </div>
          {twiceQuestion('start', when.startLocal, checked.start_time, when.startKey, startChoice, setStartChoice)}
          <div>
            <InputLabel htmlFor="event-end-time">{EVENT_WORDS.endTime}</InputLabel>
            <TextInput
              id="event-end-time"
              type="time"
              wrapperClassName="mt-1"
              required
              invalid={endTimeError !== undefined}
              aria-describedby={describedBy(endTimeError !== undefined && 'event-end-time-error')}
              {...form.register('end_time', { onChange: syncEndDate, onBlur: check })}
            />
            <InputError id="event-end-time-error" className="mt-2" message={endTimeError} />
          </div>
          {showEndDate && (
            <div>
              <InputLabel htmlFor="event-end-date">{EVENT_WORDS.endDate}</InputLabel>
              <TextInput
                id="event-end-date"
                type="date"
                wrapperClassName="mt-1"
                invalid={!!errors.end_date}
                aria-describedby={describedBy(endTimeNotAfterStart && 'event-end-date-hint', errors.end_date && 'event-end-date-error')}
                {...form.register('end_date', { onBlur: check })}
              />
              {endTimeNotAfterStart && (
                <p id="event-end-date-hint" className="mt-1 text-xs text-muted">
                  {EVENT_WORDS.endDateHint}
                </p>
              )}
              <InputError id="event-end-date-error" className="mt-2" message={errors.end_date?.message} />
            </div>
          )}
          {twiceQuestion('end', when.endLocal, checked.end_time, when.endKey, endChoice, setEndChoice)}
          <div className="@2xl:col-span-2">
            <label id="event-time-zone-label" htmlFor="event-time-zone" className="block text-sm font-bold text-primary">
              {EVENT_WORDS.timeZone}
            </label>
            <div className="mt-1">
              <Controller
                name="time_zone"
                control={form.control}
                render={({ field }) => (
                  <Combobox<string>
                    id="event-time-zone"
                    aria-labelledby="event-time-zone-label"
                    options={zoneOptions}
                    pinned={pinnedZones}
                    value={field.value === '' ? null : field.value}
                    placeholder={EVENT_WORDS.timeZonePlaceholder}
                    invalid={!!errors.time_zone}
                    aria-describedby={describedBy(errors.time_zone && 'event-time-zone-error')}
                    onChange={(zone) => {
                      field.onChange(zone);
                      // A choice in the list is one step, like leaving a field
                      setChecked({ ...whenOf(form.getValues()), time_zone: zone });
                    }}
                  />
                )}
              />
            </div>
            <InputError id="event-time-zone-error" className="mt-2" message={errors.time_zone?.message} />
          </div>
        </div>
        {/* Always in the page and in the accessibility tree: a screen reader reads each change, the first one too. Empty,
            it takes no room, but it is never `display: none`. It changes when a field is left, never at each key */}
        <p role="status" aria-live="polite" data-slot="event-when-summary" className="text-sm empty:sr-only">
          {summary}
        </p>
      </fieldset>

      <div>
        <InputLabel htmlFor="event-meeting-url">{EVENT_WORDS.fieldMeetingUrl}</InputLabel>
        <TextInput
          id="event-meeting-url"
          type="url"
          inputMode="url"
          autoComplete="off"
          wrapperClassName="mt-1"
          invalid={!!errors.meeting_url}
          aria-describedby={describedBy('event-meeting-url-hint', errors.meeting_url && 'event-meeting-url-error')}
          {...form.register('meeting_url')}
        />
        <p id="event-meeting-url-hint" className="mt-1 text-xs text-muted">
          {EVENT_WORDS.meetingUrlHint}
        </p>
        <InputError id="event-meeting-url-error" className="mt-2" message={errors.meeting_url?.message} />
      </div>

      <div>
        <InputLabel htmlFor="event-place">{EVENT_WORDS.fieldPlace}</InputLabel>
        <TextInput
          id="event-place"
          wrapperClassName="mt-1"
          maxLength={MAX_LENGTH_EVENT_PLACE}
          invalid={!!errors.place}
          aria-describedby={describedBy('event-place-hint', errors.place && 'event-place-error')}
          {...form.register('place')}
        />
        <p id="event-place-hint" className="mt-1 text-xs text-muted">
          {EVENT_WORDS.placeHint}
        </p>
        <InputError id="event-place-error" className="mt-2" message={errors.place?.message} />
      </div>

      <div>
        <MemberPicker
          id="event-host"
          label={EVENT_WORDS.fieldHost}
          communityCuid={community.cuid}
          value={host}
          onChange={(members) => {
            setHost(members);
            setHostError(null);
          }}
          excludeCuids={[]}
          max={1}
          hint={EVENT_WORDS.hostHint}
          fullPlaceholder={EVENT_WORDS.hostChosenPlaceholder}
          invalid={hostError !== null}
          errorId={hostError !== null ? 'event-host-error' : undefined}
        />
        {/* Before the save: the person learns what the change of host means for them. Empty, it stays in the
            accessibility tree (never `display: none`), so that its first text is read */}
        <p role="status" aria-live="polite" data-slot="event-host-status" className="mt-2 text-sm font-bold empty:sr-only">
          {handsOverDraft && chosenHost ? EVENT_WORDS.hostHandOver(chosenHost.name) : ''}
        </p>
        <InputError id="event-host-error" className="mt-2" message={hostError ?? undefined} />
      </div>

      <div className="flex flex-col gap-3 border-t border-border pt-4">
        {/* A refusal of the server (422), or what stops the save: always in the page and in the accessibility tree (empty,
            it takes no room, but it is never `display: none`), read when it comes */}
        <div role="alert" data-slot="event-form-error" className="text-sm font-bold text-danger-text empty:sr-only">
          {formError}
        </div>
        {isDraft && permissions.canNotifyNow && (
          <div>
            <label className="flex min-h-11 cursor-pointer items-center gap-3 md:min-h-0">
              <Checkbox
                checked={notifyNow}
                onChange={(changeEvent) => setNotifyNow(changeEvent.target.checked)}
                aria-describedby="event-publish-now-hint"
              />
              <span className="text-sm font-bold">{EVENT_WORDS.publishNow}</span>
            </label>
            <p id="event-publish-now-hint" className="mt-1 text-xs text-muted">
              {EVENT_WORDS.publishNowHint}
            </p>
          </div>
        )}
        <div className="flex flex-wrap items-center gap-2">
          {isDraft ? (
            <>
              <Button type="submit" variant="secondary" loading={busy === 'draft'}>
                {EVENT_WORDS.saveAsDraft}
              </Button>
              <Button loading={busy === 'publish'} onClick={() => void submit('publish')()}>
                {EVENT_WORDS.publish}
              </Button>
            </>
          ) : (
            <Button type="submit" loading={busy === 'save'}>
              {EVENT_WORDS.saveChanges}
            </Button>
          )}
          <Link href={cancelHref} className={buttonClasses({ variant: 'ghost' })}>
            {EVENT_WORDS.cancel}
          </Link>
        </div>
      </div>
    </form>
  );
}
