import type { MockInstance } from 'vitest';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { Author, EventFormPermissions, SpaceEvent } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { AccountTimeZoneProvider, Toaster } from '@/Components/ui';
import { EVENT_TEST_NOW, eventFixture, eventsCommunity, eventsRoute, eventsSpace } from '@/test-utils/events';
import { EVENT_WORDS } from './event-words';
import { EventForm } from './EventForm';

// An address with a script scheme, in two parts: it must never become a link
const NOT_AN_ADDRESS = ['java', 'script:alert(1)'].join('');

const shared = vi.hoisted(() => ({ visit: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  Link: ({
    children,
    href,
    preserveState: _preserveState,
    ...props
  }: {
    children: React.ReactNode;
    href: string;
    preserveState?: boolean;
  }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  router: { visit: shared.visit },
  // Nobody is signed in for the editor: it keeps no text in the browser
  usePage: () => ({ props: { auth: { user: null } } }),
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: eventsRoute, writable: true });
  Element.prototype.scrollIntoView = vi.fn();
});

const ME: Author = { cuid: 'me', name: 'Ada Lovelace', username: 'ada', avatar_url: null };
const GRACE: Author = { cuid: 'u2', name: 'Grace Hopper', username: 'grace', avatar_url: null };
const ZONES = ['UTC', 'Europe/Brussels', 'Europe/Paris', 'America/New_York', 'Asia/Singapore'];
const HOST: EventFormPermissions = { canNotifyNow: false, canManageAllEvents: false };
const MODERATOR: EventFormPermissions = { canNotifyNow: true, canManageAllEvents: false };
const OWNER: EventFormPermissions = { canNotifyNow: true, canManageAllEvents: true };

// The real formatter, kept before any spy replaces it
const RealDateTimeFormat = Intl.DateTimeFormat;

/**
 * The zone that the browser reports: only the formatter without a locale and without options speaks for the browser
 */
function browserIn(zone: string): void {
  // A function, not an arrow: the code calls it with and without `new`
  vi.spyOn(Intl, 'DateTimeFormat').mockImplementation(function (...args: ConstructorParameters<typeof Intl.DateTimeFormat>) {
    const format = new RealDateTimeFormat(...args);
    if (args[0] === undefined && args[1] === undefined) {
      return { resolvedOptions: () => ({ ...format.resolvedOptions(), timeZone: zone }) } as unknown as Intl.DateTimeFormat;
    }
    return format;
  } as unknown as typeof Intl.DateTimeFormat);
}

interface Options {
  event?: SpaceEvent | null;
  permissions?: EventFormPermissions;
  accountZone?: string | null;
}

function renderForm({ event = null, permissions = HOST, accountZone = 'Europe/Brussels' }: Options = {}) {
  return render(
    <AppDataProvider>
      <AccountTimeZoneProvider value={accountZone}>
        <main>
          <h1 id="event-form-title">{event ? 'Edit the event' : 'New event'}</h1>
          <EventForm
            community={eventsCommunity}
            space={eventsSpace}
            event={event}
            timeZones={ZONES}
            permissions={permissions}
            currentUser={ME}
          />
        </main>
        <Toaster />
      </AccountTimeZoneProvider>
    </AppDataProvider>,
  );
}

/**
 * Puts a value in a field and leaves it: the two steps of a person
 */
function fill(label: string, value: string, leave = true): HTMLInputElement {
  const input = screen.getByLabelText<HTMLInputElement>(label);
  fireEvent.change(input, { target: { value } });
  if (leave) {
    fireEvent.blur(input);
  }
  return input;
}

function fillTimes(date = '2026-11-18', start = '17:00', end = '18:00') {
  fill('Title', 'Weekly review office hour');
  fill('Start date', date);
  fill('Start time', start);
  fill('End time', end);
}

const zoneField = () => screen.getByRole<HTMLInputElement>('combobox', { name: 'Time zone' });

async function chooseZone(user: ReturnType<typeof userEvent.setup>, search: string, option: RegExp) {
  await user.click(zoneField());
  await user.clear(zoneField());
  await user.paste(search);
  await user.click(await screen.findByRole('option', { name: option }));
}

const summary = () => document.querySelector('[data-slot="event-when-summary"]')?.textContent ?? '';
const formError = () => document.querySelector('[data-slot="event-form-error"]');
const saved = (changes: Record<string, unknown> = {}) => ({
  ok: true as const,
  message: '',
  data: eventFixture('e1', 'Weekly review office hour', changes),
});

/**
 * What the list of the answers gives the form: the number of Yes answers, in `counts`
 */
const yesAnswers = (yes: number) => ({
  ok: true as const,
  message: '',
  data: {
    items: [],
    pagination: { limit: 1, offset: 0, previousOffset: null, nextOffset: null, currentPage: 1, pageCount: 1, totalCount: yes },
    counts: { yes, replay: 0, no: 0 },
  },
});
const capacityStatus = () => document.querySelector('[data-slot="event-capacity-status"]') as HTMLElement;

// Each key in the list of the zones renders up to 50 entries: seconds on a busy machine
describe('the form of the host of an event (#1514, design 4.2, 6.3 and 11.1)', { timeout: 30_000 }, () => {
  let create: MockInstance<typeof knowiiApiClient.communities.events.create>;
  let update: MockInstance<typeof knowiiApiClient.communities.events.update>;
  let setState: MockInstance<typeof knowiiApiClient.communities.events.setState>;
  let upload: MockInstance<typeof knowiiApiClient.communities.uploads.image>;
  let answersList: MockInstance<typeof knowiiApiClient.communities.events.answers.list>;

  beforeEach(() => {
    // Only the clock of `new Date()`: the current year decides whether a date names its year
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(EVENT_TEST_NOW);
    shared.visit.mockClear();
    browserIn('Europe/Brussels');
    create = vi.spyOn(knowiiApiClient.communities.events, 'create').mockResolvedValue(saved({ state: 'draft' }));
    update = vi.spyOn(knowiiApiClient.communities.events, 'update').mockResolvedValue(saved());
    setState = vi.spyOn(knowiiApiClient.communities.events, 'setState').mockResolvedValue(saved());
    upload = vi.spyOn(knowiiApiClient.communities.uploads, 'image').mockResolvedValue({
      ok: true,
      message: '',
      data: {
        cuid: 'up1',
        url: '/uploads/cover.webp',
        mime_type: 'image/webp',
        width: 1600,
        height: 900,
        size_bytes: 1000,
        alt: 'x',
        created_at: new Date(),
      },
    });
    // The number of Yes answers of a published or cancelled event (#1515): none, unless a test says so
    answersList = vi.spyOn(knowiiApiClient.communities.events.answers, 'list').mockResolvedValue(yesAnswers(0));
    vi.spyOn(knowiiApiClient.communities.members, 'search').mockResolvedValue([GRACE]);
    vi.spyOn(knowiiApiClient.markdown, 'preview').mockResolvedValue('<p>Preview</p>');
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  describe('the fields (rule 24)', () => {
    it('has the fields of the design, in their order, each with a label that is not a placeholder', () => {
      renderForm();

      const labels = [
        'Title',
        'Description',
        'Cover description',
        'Start date',
        'Start time',
        'End time',
        'Time zone',
        'Meeting address',
        'Place',
        // The three fields of the answers (#1515), after "Place" and before "Host"
        'Every member',
        'Members who answer Yes',
        'Limit of places',
        'A replay will exist',
        'Host',
      ];
      // The label of the zone names its field and the button of its list
      const fields = labels.map((label) => (label === 'Time zone' ? zoneField() : screen.getByLabelText(label)));
      for (let index = 1; index < fields.length; index++) {
        const before = fields[index - 1] as HTMLElement;
        const after = fields[index] as HTMLElement;
        expect(before.compareDocumentPosition(after) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      }
      // The cover is a group with a button, between the description and its own description
      const cover = screen.getByRole('group', { name: 'Cover' });
      expect(within(cover).getByRole('button', { name: 'Choose an image' })).toBeInTheDocument();
      expect((fields[1] as HTMLElement).compareDocumentPosition(cover) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      expect(cover.compareDocumentPosition(fields[2] as HTMLElement) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      // The names come from labels: a field with a placeholder keeps its label
      expect(screen.getByLabelText('Title')).not.toHaveAttribute('placeholder');
      expect(screen.getByText('Leave empty when the image is decoration')).toBeInTheDocument();
    });

    it('puts the four fields of the time in one group "When", with native date and time fields', () => {
      renderForm();

      const group = screen.getByRole('group', { name: 'When' });
      expect(group.tagName).toBe('FIELDSET');
      expect(within(group).getByLabelText('Start date')).toHaveAttribute('type', 'date');
      expect(within(group).getByLabelText('Start time')).toHaveAttribute('type', 'time');
      expect(within(group).getByLabelText('End time')).toHaveAttribute('type', 'time');
      expect(within(group).getByRole('combobox', { name: 'Time zone' })).toBeInTheDocument();
    });

    it('names the reader as the host of a new event', () => {
      renderForm();

      expect(screen.getByRole('button', { name: 'Remove Ada Lovelace' })).toBeInTheDocument();
      expect(screen.getByText('One member. Type a name, then choose with the arrows and Enter.')).toBeInTheDocument();
    });
  });

  describe('"When" (rule 25)', () => {
    it('starts in the zone of the account, with the place and the offset in the field', () => {
      renderForm({ accountZone: 'America/New_York' });

      expect(zoneField().value).toMatch(/^New York, America \(UTC-[45]\)$/);
    });

    it('starts in the zone of the browser without a zone of the account', () => {
      browserIn('Asia/Singapore');
      renderForm({ accountZone: null });

      expect(zoneField().value).toBe('Singapore, Asia (UTC+8)');
    });

    it('starts an edit in the zone of the event, with its local date and times', () => {
      renderForm({ event: eventFixture('e1', 'Weekly review', { time_zone: 'Asia/Singapore' }), accountZone: 'America/New_York' });

      expect(zoneField().value).toBe('Singapore, Asia (UTC+8)');
      // 18 November 2026, 16:00 UTC is Thursday 19 November, 00:00 in Singapore
      expect(screen.getByLabelText('Start date')).toHaveValue('2026-11-19');
      expect(screen.getByLabelText('Start time')).toHaveValue('00:00');
      expect(screen.getByLabelText('End time')).toHaveValue('01:00');
    });

    it('keeps the zone of the account and the zone of the browser at the top of the list, and offers only the zones of the server', async () => {
      browserIn('Asia/Singapore');
      renderForm({ accountZone: 'America/New_York' });
      const user = userEvent.setup();

      await user.click(zoneField());
      const options = within(await screen.findByRole('listbox', { name: 'Time zone' }))
        .getAllByRole('option')
        .map((option) => option.textContent ?? '');

      expect(options).toHaveLength(ZONES.length);
      expect(options[0]).toMatch(/^New York, America/);
      expect(options[1]).toMatch(/^Singapore, Asia/);
    });

    it('shows "End date" only when the end time is not after the start time, set to the next day', () => {
      renderForm();
      fillTimes('2026-11-18', '17:00', '18:00');
      expect(screen.queryByLabelText('End date')).toBeNull();

      fill('End time', '01:00');
      expect(screen.getByLabelText('End date')).toHaveValue('2026-11-19');
      expect(screen.getByLabelText('End date')).toHaveAttribute('type', 'date');

      // The same time is not after the start either: 24 hours
      fill('End time', '17:00');
      expect(screen.getByLabelText('End date')).toHaveValue('2026-11-19');

      // The next day follows the start date
      fill('Start date', '2026-12-31');
      expect(screen.getByLabelText('End date')).toHaveValue('2027-01-01');

      fill('End time', '19:00');
      expect(screen.queryByLabelText('End date')).toBeNull();
    });

    it('shows the end date of an event that ends on the next day', () => {
      renderForm({
        event: eventFixture('e1', 'Late', { starts_at: '2026-11-18T22:00:00.000000Z', ends_at: '2026-11-19T00:00:00.000000Z' }),
      });

      expect(screen.getByLabelText('Start time')).toHaveValue('23:00');
      expect(screen.getByLabelText('End time')).toHaveValue('01:00');
      expect(screen.getByLabelText('End date')).toHaveValue('2026-11-19');
    });

    describe('an event of 24 hours over a change of the clocks: it ends on the next day, with an end time after its start time', () => {
      // Brussels, 27 March 2027, 12:00 (UTC+1) to 28 March, 13:00 (UTC+2): the clocks go forward in the night
      const overTheChange = () =>
        eventFixture('e1', 'A full day', { starts_at: '2027-03-27T11:00:00.000000Z', ends_at: '2027-03-28T11:00:00.000000Z', host: ME });

      it('shows "End date" with the stored day, without the sentence about the end time', () => {
        renderForm({ event: overTheChange() });

        expect(screen.getByLabelText('Start date')).toHaveValue('2027-03-27');
        expect(screen.getByLabelText('Start time')).toHaveValue('12:00');
        expect(screen.getByLabelText('End time')).toHaveValue('13:00');
        expect(screen.getByLabelText('End date')).toHaveValue('2027-03-28');
        // "The end time is not after the start time" is not true here
        expect(screen.queryByText(EVENT_WORDS.endDateHint)).toBeNull();
        expect(screen.getByLabelText('End date')).not.toHaveAttribute('aria-describedby');
        expect(summary()).toBe(
          'Saturday 27 March 2027, 12:00, Central European Standard Time (UTC+1) to Sunday 28 March 2027, 13:00, Central European Summer Time (UTC+2). In your zone: the same.',
        );
      });

      it('keeps the end date when a time changes, and sends the end on that day', async () => {
        renderForm({ event: overTheChange() });

        fill('End time', '12:30');
        expect(screen.getByLabelText('End date')).toHaveValue('2027-03-28');
        fill('Start time', '11:30');
        expect(screen.getByLabelText('End date')).toHaveValue('2027-03-28');
        fill('Start time', '12:00');

        await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));

        await waitFor(() =>
          expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', {
            starts_at: '2027-03-27T12:00:00+01:00',
            ends_at: '2027-03-28T12:30:00+02:00',
            time_zone: 'Europe/Brussels',
          }),
        );
      });

      it('changes the end date only when the person changes it', async () => {
        renderForm({ event: overTheChange() });

        fill('End date', '2027-03-27');
        // The field stays for the rest of the edit: the person can change it again
        expect(screen.getByLabelText('End date')).toHaveValue('2027-03-27');
        await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));

        await waitFor(() =>
          expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', {
            starts_at: '2027-03-27T12:00:00+01:00',
            ends_at: '2027-03-27T13:00:00+01:00',
            time_zone: 'Europe/Brussels',
          }),
        );
      });

      it('sends no time when nothing changed', async () => {
        renderForm({ event: overTheChange() });

        await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));

        await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', {}));
      });
    });
  });

  describe('the status of "When" (rule 26)', () => {
    it('repeats the time in words with the zone and its offset, and says "the same" for a reader in that zone', () => {
      renderForm();
      fillTimes();

      expect(summary()).toBe('Wednesday 18 November 2026, 17:00 to 18:00, Central European Standard Time (UTC+1). In your zone: the same.');
      const status = document.querySelector('[data-slot="event-when-summary"]');
      expect(status).toHaveAttribute('role', 'status');
      expect(status).toHaveAttribute('aria-live', 'polite');
    });

    it('gives the time of the reader when the event is entered in another zone', async () => {
      renderForm({ accountZone: 'Europe/Brussels' });
      const user = userEvent.setup();
      fillTimes('2026-11-18', '11:00', '12:00');

      await chooseZone(user, 'new york', /^New York, America/);

      await waitFor(() =>
        expect(summary()).toBe(
          'Wednesday 18 November 2026, 11:00 to 12:00, Eastern Standard Time (UTC-5). In your zone: Wednesday 18 November, 17:00 to 18:00 Central European Standard Time.',
        ),
      );
    });

    it('names the other day of the reader: midnight in Singapore is the evening before in Brussels', async () => {
      renderForm({ accountZone: 'Europe/Brussels' });
      const user = userEvent.setup();
      fillTimes('2026-11-19', '00:00', '01:00');

      await chooseZone(user, 'singapore', /^Singapore, Asia/);

      await waitFor(() =>
        expect(summary()).toBe(
          'Thursday 19 November 2026, 00:00 to 01:00, Singapore Standard Time (UTC+8). In your zone: Wednesday 18 November, 17:00 to 18:00 Central European Standard Time.',
        ),
      );
    });

    it('changes when the person leaves a field, never at each key', () => {
      renderForm();
      fillTimes();
      const before = summary();

      const field = fill('Start time', '16:00', false);
      expect(summary()).toBe(before);

      fireEvent.blur(field);
      expect(summary()).toBe('Wednesday 18 November 2026, 16:00 to 18:00, Central European Standard Time (UTC+1). In your zone: the same.');
    });

    it('waits for the end of a press on a button: the sentence would move the button away from under the pointer', async () => {
      renderForm();
      fillTimes();
      const before = summary();
      const field = fill('Start time', '16:00', false);
      const button = screen.getByRole('button', { name: 'Save as draft' });

      // A press on the button takes the focus away from the field before the click
      fireEvent.pointerDown(button);
      fireEvent.blur(field);
      expect(summary()).toBe(before);

      fireEvent.pointerUp(button);
      await waitFor(() =>
        expect(summary()).toBe(
          'Wednesday 18 November 2026, 16:00 to 18:00, Central European Standard Time (UTC+1). In your zone: the same.',
        ),
      );
    });

    it('saves with one click on the button right after a time is typed', async () => {
      renderForm();
      fill('Title', 'Weekly review office hour');
      fill('Start date', '2026-11-18');
      fill('Start time', '17:00');
      // The person types the end time and clicks "Save as draft" at once, without leaving the field first
      const user = userEvent.setup();
      await user.click(screen.getByLabelText('End time'));
      fireEvent.change(screen.getByLabelText('End time'), { target: { value: '18:00' } });

      await user.click(screen.getByRole('button', { name: 'Save as draft' }));

      await waitFor(() => expect(create).toHaveBeenCalledTimes(1));
      expect(create.mock.calls[0]?.[2]).toMatchObject({ starts_at: '2026-11-18T17:00:00+01:00', ends_at: '2026-11-18T18:00:00+01:00' });
    });

    it('is empty until the date and the two times are there', () => {
      renderForm();
      fill('Start date', '2026-11-18');
      fill('Start time', '17:00');

      expect(summary()).toBe('');
    });

    it('names the day of an end on the next day', () => {
      renderForm();
      fillTimes('2026-11-18', '23:00', '01:00');
      fireEvent.blur(screen.getByLabelText('End date'));

      expect(summary()).toBe(
        'Wednesday 18 November 2026, 23:00 to Thursday 19 November 2026, 01:00, Central European Standard Time (UTC+1). In your zone: the same.',
      );
    });

    it('refuses a time that the zone skips, with the two times around the gap, and sends nothing', async () => {
      renderForm();
      fillTimes('2027-03-28', '02:30', '04:00');

      const alert = screen.getByText('This time does not exist in this zone. Choose 01:59 or earlier, or 03:00 or later.');
      expect(alert).toHaveAttribute('role', 'alert');
      expect(alert).toHaveAttribute('id', 'event-start-time-error');
      expect(screen.getByLabelText('Start time')).toHaveAttribute('aria-invalid', 'true');
      expect(screen.getByLabelText('Start time')).toHaveAttribute('aria-describedby', 'event-start-time-error');
      expect(summary()).toBe('');

      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));
      await waitFor(() => expect(formError()).toHaveTextContent(EVENT_WORDS.fixTheFields));
      expect(create).not.toHaveBeenCalled();
    });

    it('names the day before when the clocks go forward at midnight (28 March 2027, 00:30 in the Azores)', () => {
      renderForm({ accountZone: 'Atlantic/Azores' });
      fillTimes('2027-03-28', '00:30', '02:00');

      // 23:59 is a time of 27 March: "Choose 23:59 or earlier" would name a time after the one that was typed
      const alert = screen.getByText('This time does not exist in this zone. Choose 23:59 the day before or earlier, or 01:00 or later.');
      expect(alert).toHaveAttribute('role', 'alert');
      expect(alert).toHaveAttribute('id', 'event-start-time-error');
      expect(screen.queryByText(EVENT_WORDS.skippedTime('23:59', '01:00'))).toBeNull();
    });

    it('asks "summer time or winter time?" for a time that exists twice, and sends the offset of the answer', async () => {
      renderForm();
      fillTimes('2027-10-31', '02:30', '04:00');

      const group = screen.getByRole('group', { name: 'The start: 02:30 in summer time (UTC+2) or in winter time (UTC+1)?' });
      const summer = within(group).getByRole('radio', { name: 'Summer time (UTC+2)' });
      const winter = within(group).getByRole('radio', { name: 'Winter time (UTC+1)' });
      expect(summer).not.toBeChecked();
      expect(winter).not.toBeChecked();
      // The question appears before the field that has the focus: the status of "When" says it, for a screen reader
      expect(summary()).toBe('This time exists twice on this day. Choose summer time or winter time below.');
      expect(document.querySelector('[data-slot="event-when-summary"]')).toHaveAttribute('aria-live', 'polite');

      // Without an answer nothing is sent
      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));
      expect(await within(group).findByText('Choose summer time or winter time')).toHaveAttribute('role', 'alert');
      expect(create).not.toHaveBeenCalled();

      await userEvent.click(winter);
      expect(summary()).toBe('Sunday 31 October 2027, 02:30 to 04:00, Central European Standard Time (UTC+1). In your zone: the same.');
      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));

      await waitFor(() => expect(create).toHaveBeenCalled());
      expect(create.mock.calls[0]?.[2]).toMatchObject({
        starts_at: '2027-10-31T02:30:00+01:00',
        ends_at: '2027-10-31T04:00:00+01:00',
        time_zone: 'Europe/Brussels',
      });
    });

    it('sends the summer offset when the person chooses the summer time', async () => {
      renderForm();
      fillTimes('2027-10-31', '02:30', '04:00');

      await userEvent.click(screen.getByRole('radio', { name: 'Summer time (UTC+2)' }));
      expect(summary()).toBe(
        'Sunday 31 October 2027, 02:30, Central European Summer Time (UTC+2) to 04:00, Central European Standard Time (UTC+1). In your zone: the same.',
      );
      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));

      await waitFor(() => expect(create).toHaveBeenCalled());
      expect(create.mock.calls[0]?.[2]).toMatchObject({ starts_at: '2027-10-31T02:30:00+02:00', ends_at: '2027-10-31T04:00:00+01:00' });
    });

    it('says when the end is not after the start, and when the event is too long', () => {
      renderForm();
      fillTimes('2026-11-18', '17:00', '16:00');
      // The person puts the end on the day of the start
      fill('End date', '2026-11-18');
      expect(screen.getByText('The end must be after the start')).toHaveAttribute('role', 'alert');
      expect(screen.getByText('The end must be after the start')).toHaveAttribute('id', 'event-end-time-error');

      fill('End date', '2026-11-20');
      expect(screen.getByText('An event lasts 24 hours at most')).toHaveAttribute('role', 'alert');
      expect(summary()).toBe('');
    });
  });

  describe('the buttons (rule 27)', () => {
    it('offers "Save as draft" and "Publish" for a new event and for a draft', () => {
      const { unmount } = renderForm();
      expect(screen.getByRole('button', { name: 'Save as draft' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Publish' })).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Save changes' })).toBeNull();
      expect(screen.getByRole('link', { name: 'Cancel' })).toHaveAttribute('href', '/community/tests/space/office-hours');
      unmount();

      renderForm({ event: eventFixture('e1', 'Weekly review', { state: 'draft' }) });
      expect(screen.getByRole('button', { name: 'Save as draft' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Publish' })).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Cancel' })).toHaveAttribute(
        'href',
        '/community/tests/space/office-hours/event/weekly-review',
      );
    });

    it.each(['published', 'cancelled'])('offers only "Save changes" for a %s event', (state) => {
      renderForm({ event: eventFixture('e1', 'Weekly review', { state }), permissions: OWNER });

      expect(screen.getByRole('button', { name: 'Save changes' })).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Save as draft' })).toBeNull();
      expect(screen.queryByRole('button', { name: 'Publish' })).toBeNull();
      expect(screen.queryByRole('checkbox', { name: 'Publish now' })).toBeNull();
    });

    it('shows "Publish now" with its hint only to a reader who may tell the members now', () => {
      const { unmount } = renderForm({ permissions: HOST });
      expect(screen.queryByRole('checkbox', { name: 'Publish now' })).toBeNull();
      unmount();

      renderForm({ permissions: MODERATOR });
      const checkbox = screen.getByRole('checkbox', { name: 'Publish now' });
      expect(checkbox).not.toBeChecked();
      expect(checkbox).toHaveAccessibleDescription('Without it, members are told after 5 minutes.');
    });

    it('sends notify_now with a publication when "Publish now" is ticked, and never with a draft', async () => {
      renderForm({ permissions: MODERATOR });
      fillTimes();
      await userEvent.click(screen.getByRole('checkbox', { name: 'Publish now' }));

      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));
      await waitFor(() => expect(create).toHaveBeenCalledTimes(1));
      expect(create.mock.calls[0]?.[2]).not.toHaveProperty('notify_now');

      await userEvent.click(screen.getByRole('button', { name: 'Publish' }));
      await waitFor(() => expect(create).toHaveBeenCalledTimes(2));
      expect(create.mock.calls[1]?.[2]).toMatchObject({ state: 'published', notify_now: true });
    });
  });

  describe('a new event (rules 26 and 29)', () => {
    it('saves a draft with the instants and their offset, the zone, and no field that the form does not have', async () => {
      renderForm();
      fillTimes();
      fill('Meeting address', 'https://meet.example.com/abc');
      fill('Place', 'Room 2');

      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));

      await waitFor(() => expect(create).toHaveBeenCalledTimes(1));
      expect(create.mock.calls[0]).toEqual([
        'c1',
        's1',
        {
          title: 'Weekly review office hour',
          body: '',
          starts_at: '2026-11-18T17:00:00+01:00',
          ends_at: '2026-11-18T18:00:00+01:00',
          time_zone: 'Europe/Brussels',
          meeting_url: 'https://meet.example.com/abc',
          place: 'Room 2',
          cover_upload_cuid: null,
          cover_alt: null,
          // The three fields of the answers, as a new form starts (#1515)
          location_visibility: 'members',
          capacity: null,
          replay_expected: false,
          state: 'draft',
        },
      ]);
      expect(await screen.findByText('Draft saved')).toBeInTheDocument();
      expect(shared.visit).toHaveBeenCalledWith('/community/tests/space/office-hours/event/weekly-review-office-hour');
    });

    it('publishes, says so and opens the page of the event', async () => {
      create.mockResolvedValue(saved({ slug: 'the-slug-of-the-server' }));
      renderForm();
      fillTimes();

      await userEvent.click(screen.getByRole('button', { name: 'Publish' }));

      await waitFor(() => expect(create).toHaveBeenCalledTimes(1));
      expect(create.mock.calls[0]?.[2]).toMatchObject({ state: 'published' });
      expect(create.mock.calls[0]?.[2]).not.toHaveProperty('notify_now');
      // A new form sends the three fields of the answers as they start (#1515)
      expect(create.mock.calls[0]?.[2]).toMatchObject({ location_visibility: 'members', capacity: null, replay_expected: false });
      expect(await screen.findByText('Event published')).toBeInTheDocument();
      expect(shared.visit).toHaveBeenCalledWith('/community/tests/space/office-hours/event/the-slug-of-the-server');
    });

    it('sends an end on the next day with its date', async () => {
      renderForm();
      fillTimes('2026-11-18', '23:00', '01:00');

      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));

      await waitFor(() => expect(create).toHaveBeenCalled());
      expect(create.mock.calls[0]?.[2]).toMatchObject({ starts_at: '2026-11-18T23:00:00+01:00', ends_at: '2026-11-19T01:00:00+01:00' });
    });

    it('sends the times in the zone that the host chose', async () => {
      renderForm();
      const user = userEvent.setup();
      fillTimes('2026-11-18', '11:00', '12:00');
      await chooseZone(user, 'new york', /^New York, America/);

      await user.click(screen.getByRole('button', { name: 'Save as draft' }));

      await waitFor(() => expect(create).toHaveBeenCalled());
      expect(create.mock.calls[0]?.[2]).toMatchObject({
        starts_at: '2026-11-18T11:00:00-05:00',
        ends_at: '2026-11-18T12:00:00-05:00',
        time_zone: 'America/New_York',
      });
    });
  });

  describe('the cover (rule 28)', () => {
    const file = new File(['x'], 'cover.png', { type: 'image/png' });
    const fileInput = () => document.querySelector<HTMLInputElement>('#event-cover-file') as HTMLInputElement;

    it('uploads the image when it is chosen, with the description that the host typed, and shows a preview', async () => {
      renderForm();
      fill('Cover description', 'The team at a table');

      await userEvent.upload(fileInput(), file);

      await waitFor(() => expect(upload).toHaveBeenCalledWith('c1', file, 'The team at a table'));
      expect(await screen.findByRole('img', { name: 'The team at a table' })).toHaveAttribute('src', '/uploads/cover.webp');
      expect(screen.getByRole('button', { name: 'Change the image' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Remove the cover' })).toBeInTheDocument();
      expect(screen.getByText('Cover added')).toHaveAttribute('role', 'status');
    });

    it('sends "Cover of an event" to the upload without a description, and never stores it as the description', async () => {
      renderForm();
      fillTimes();

      await userEvent.upload(fileInput(), file);
      await waitFor(() => expect(upload).toHaveBeenCalledWith('c1', file, 'Cover of an event'));
      // A decoration: the preview has an empty description
      await waitFor(() => expect(document.querySelector('img[src="/uploads/cover.webp"]')).toHaveAttribute('alt', ''));

      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));
      await waitFor(() => expect(create).toHaveBeenCalled());
      expect(create.mock.calls[0]?.[2]).toMatchObject({ cover_upload_cuid: 'up1', cover_alt: null });
    });

    it('removes the cover and gives the focus to the button that chooses an image', async () => {
      const cover = {
        cuid: 'up0',
        url: '/uploads/old.webp',
        mime_type: 'image/webp',
        width: 1600,
        height: 900,
        size_bytes: 1000,
        alt: 'x',
      };
      renderForm({ event: eventFixture('e1', 'Weekly review', { cover, cover_alt: 'An old picture', state: 'draft' }) });
      expect(screen.getByRole('img', { name: 'An old picture' })).toBeInTheDocument();

      await userEvent.click(screen.getByRole('button', { name: 'Remove the cover' }));

      expect(screen.queryByRole('img')).toBeNull();
      await waitFor(() => expect(screen.getByRole('button', { name: 'Choose an image' })).toHaveFocus());
      expect(screen.getByText('Cover removed')).toHaveAttribute('role', 'status');

      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));
      await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', { cover_upload_cuid: null }));
    });

    it('shows the refusal of the server next to the cover, and keeps the form', async () => {
      upload.mockResolvedValue({ ok: false, status: 400, message: 'The image is not valid.', errors: {} });
      renderForm();

      await userEvent.upload(fileInput(), file);

      const alert = await screen.findByText('The image is not valid.');
      expect(alert).toHaveAttribute('role', 'alert');
      expect(screen.queryByRole('button', { name: 'Remove the cover' })).toBeNull();
    });

    it('refuses a file that is no image, without a request', async () => {
      renderForm();

      fireEvent.change(fileInput(), { target: { files: [new File(['x'], 'notes.pdf', { type: 'application/pdf' })] } });

      expect(await screen.findByText('Choose a JPEG, PNG, WebP or GIF image.')).toHaveAttribute('role', 'alert');
      expect(upload).not.toHaveBeenCalled();
    });
  });

  describe('the host (rules 24 and 29)', () => {
    async function nameGraceAsHost(user: ReturnType<typeof userEvent.setup>) {
      await user.click(screen.getByRole('button', { name: 'Remove Ada Lovelace' }));
      await user.type(screen.getByRole('combobox', { name: 'Host' }), 'gra');
      await user.click(await screen.findByRole('option', { name: /Grace Hopper/ }));
    }

    it('sends no host when the reader stays the host, and the other member when they name one', async () => {
      renderForm();
      const user = userEvent.setup();
      fillTimes();

      await user.click(screen.getByRole('button', { name: 'Publish' }));
      await waitFor(() => expect(create).toHaveBeenCalledTimes(1));
      expect(create.mock.calls[0]?.[2]).not.toHaveProperty('host_cuid');

      await nameGraceAsHost(user);
      await user.click(screen.getByRole('button', { name: 'Publish' }));
      await waitFor(() => expect(create).toHaveBeenCalledTimes(2));
      expect(create.mock.calls[1]?.[2]).toMatchObject({ host_cuid: 'u2', state: 'published' });
      expect(shared.visit).toHaveBeenLastCalledWith('/community/tests/space/office-hours/event/weekly-review-office-hour');
    });

    it('tells the creator before the save that a draft of another host is no longer theirs to see, and goes to the space after it', async () => {
      renderForm({ permissions: HOST });
      const user = userEvent.setup();
      fillTimes();
      expect(screen.queryByText(/You will not see it any more/)).toBeNull();

      await nameGraceAsHost(user);

      const notice = screen.getByText(
        'If you save this as a draft, only Grace Hopper, the owner and the admins see it. You will not see it any more.',
      );
      expect(notice).toHaveAttribute('role', 'status');
      await user.click(screen.getByRole('button', { name: 'Save as draft' }));

      await waitFor(() => expect(create).toHaveBeenCalled());
      expect(create.mock.calls[0]?.[2]).toMatchObject({ host_cuid: 'u2', state: 'draft' });
      expect(await screen.findByText('Draft saved')).toBeInTheDocument();
      expect(shared.visit).toHaveBeenCalledWith('/community/tests/space/office-hours');
    });

    it('says nothing of the sort to the owner and the admins, who see every draft', async () => {
      renderForm({ permissions: OWNER });
      const user = userEvent.setup();
      fillTimes();

      await nameGraceAsHost(user);
      expect(screen.queryByText(/You will not see it any more/)).toBeNull();
      await user.click(screen.getByRole('button', { name: 'Save as draft' }));

      await waitFor(() => expect(shared.visit).toHaveBeenCalledWith('/community/tests/space/office-hours/event/weekly-review-office-hour'));
    });

    it('gives the focus to the field "Host" when the keyboard removes the host: the field was disabled until then', async () => {
      renderForm();
      const user = userEvent.setup();
      const field = screen.getByRole('combobox', { name: 'Host' });
      expect(field).toBeDisabled();
      const remove = screen.getByRole('button', { name: 'Remove Ada Lovelace' });

      remove.focus();
      await user.keyboard('{Enter}');

      expect(screen.queryByRole('button', { name: 'Remove Ada Lovelace' })).toBeNull();
      await waitFor(() => expect(field).toHaveFocus());
      expect(field).toBeEnabled();
    });

    it('asks for a host when there is none, and sends nothing', async () => {
      renderForm();
      const user = userEvent.setup();
      fillTimes();
      await user.click(screen.getByRole('button', { name: 'Remove Ada Lovelace' }));

      await user.click(screen.getByRole('button', { name: 'Save as draft' }));

      expect(await screen.findByText('Choose a host')).toHaveAttribute('role', 'alert');
      expect(create).not.toHaveBeenCalled();
    });
  });

  describe('a change of an event (rules 24, 27 and 29)', () => {
    const draft = () => eventFixture('e1', 'Weekly review', { state: 'draft', body: 'Bring questions', place: 'Room 2', host: ME });
    const published = () => eventFixture('e1', 'Weekly review', { body: 'Bring questions', host: ME });
    const nameGrace = async (user: ReturnType<typeof userEvent.setup>) => {
      await user.click(screen.getByRole('button', { name: 'Remove Ada Lovelace' }));
      await user.type(screen.getByRole('combobox', { name: 'Host' }), 'gra');
      await user.click(await screen.findByRole('option', { name: /Grace Hopper/ }));
    };

    it('sends only what changed, and none of the three fields of the answers when they did not change', async () => {
      renderForm({ event: published() });
      fill('Title', 'A better title');

      await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));

      await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', { title: 'A better title' }));
      expect(setState).not.toHaveBeenCalled();
      expect(await screen.findByText('Changes saved')).toBeInTheDocument();
      expect(shared.visit).toHaveBeenCalledWith('/community/tests/space/office-hours/event/weekly-review-office-hour');
    });

    it('sends the three values of the time together when one of them changes', async () => {
      renderForm({ event: published() });
      fill('End time', '19:00');

      await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));

      await waitFor(() =>
        expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', {
          starts_at: '2026-11-18T17:00:00+01:00',
          ends_at: '2026-11-18T19:00:00+01:00',
          time_zone: 'Europe/Brussels',
        }),
      );
    });

    it('removes an address and a place with null', async () => {
      renderForm({ event: eventFixture('e1', 'Weekly review', { place: 'Room 2', host: ME }) });
      fill('Meeting address', '');
      fill('Place', '');

      await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));

      await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', { meeting_url: null, place: null }));
    });

    it('saves a draft with one call, and says "Draft saved"', async () => {
      renderForm({ event: draft() });
      fill('Place', 'Room 3');

      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));

      await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', { place: 'Room 3' }));
      expect(setState).not.toHaveBeenCalled();
      expect(await screen.findByText('Draft saved')).toBeInTheDocument();
    });

    it('publishes a draft: the changes first, then the state, with "Publish now" when it is ticked', async () => {
      const order: string[] = [];
      update.mockImplementation(() => {
        order.push('update');
        return Promise.resolve(saved({ state: 'draft' }));
      });
      setState.mockImplementation(() => {
        order.push('state');
        return Promise.resolve(saved({ slug: 'weekly-review' }));
      });
      renderForm({ event: draft(), permissions: MODERATOR });
      fill('Title', 'A better title');
      await userEvent.click(screen.getByRole('checkbox', { name: 'Publish now' }));

      await userEvent.click(screen.getByRole('button', { name: 'Publish' }));

      await waitFor(() => expect(setState).toHaveBeenCalledWith('c1', 's1', 'e1', 'published', true));
      expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', { title: 'A better title' });
      expect(order).toEqual(['update', 'state']);
      expect(await screen.findByText('Event published')).toBeInTheDocument();
      expect(shared.visit).toHaveBeenCalledWith('/community/tests/space/office-hours/event/weekly-review');
    });

    it('sends the new host with the other changes when the owner or an admin publishes a draft: two requests', async () => {
      const order: string[] = [];
      update.mockImplementation((_community, _space, _event, input) => {
        order.push(`update ${Object.keys(input).join(',')}`);
        return Promise.resolve(saved({ state: 'draft' }));
      });
      setState.mockImplementation(() => {
        order.push('state');
        return Promise.resolve(saved());
      });
      renderForm({ event: draft(), permissions: OWNER });
      const user = userEvent.setup();
      fill('Title', 'A better title');
      await nameGrace(user);

      await user.click(screen.getByRole('button', { name: 'Publish' }));

      await waitFor(() => expect(shared.visit).toHaveBeenCalled());
      expect(order).toEqual(['update title,host_cuid', 'state']);
      expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', { title: 'A better title', host_cuid: 'u2' });
    });

    describe('a publication whose requests do not all work', () => {
      // What the server answers after the first request: the draft with the new title
      const draftAnswer = () => saved({ state: 'draft', title: 'A better title', body: 'Bring questions', place: 'Room 2', host: ME });
      const publishedAnswer = () => saved({ title: 'A better title', body: 'Bring questions', place: 'Room 2', host: ME });
      const refused = (status: number, message: string) => ({ ok: false as const, status, message, errors: {} });

      it('saves nothing when the first request fails: the message of the server, and the form of a draft', async () => {
        update.mockResolvedValue(refused(403, 'This action is unauthorized.'));
        renderForm({ event: draft() });
        fill('Title', 'A better title');

        await userEvent.click(screen.getByRole('button', { name: 'Publish' }));

        await waitFor(() => expect(formError()?.textContent).toBe('This action is unauthorized.'));
        expect(setState).not.toHaveBeenCalled();
        expect(screen.getByRole('button', { name: 'Save as draft' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Publish' })).toBeInTheDocument();
        expect(shared.visit).not.toHaveBeenCalled();
        expect(screen.queryByText('Event published')).toBeNull();
      });

      it('says that the changes are saved when the publication fails, and the next publication sends them no more', async () => {
        update.mockResolvedValue(draftAnswer());
        setState.mockResolvedValueOnce(refused(422, 'An event that started cannot be published.'));
        setState.mockResolvedValueOnce(publishedAnswer());
        renderForm({ event: draft() });
        fill('Title', 'A better title');

        await userEvent.click(screen.getByRole('button', { name: 'Publish' }));

        await waitFor(() =>
          expect(formError()?.textContent).toBe(
            'An event that started cannot be published. Your changes are saved. The event is not published.',
          ),
        );
        expect(update).toHaveBeenLastCalledWith('c1', 's1', 'e1', { title: 'A better title' });
        // The event is still a draft: the form too
        expect(screen.getByRole('button', { name: 'Save as draft' })).toBeInTheDocument();
        expect(shared.visit).not.toHaveBeenCalled();
        expect(screen.queryByText('Event published')).toBeNull();

        await userEvent.click(screen.getByRole('button', { name: 'Publish' }));

        await waitFor(() =>
          expect(shared.visit).toHaveBeenCalledWith('/community/tests/space/office-hours/event/weekly-review-office-hour'),
        );
        // The title is on the server: the form compares with the event that the server answered
        expect(update).toHaveBeenLastCalledWith('c1', 's1', 'e1', {});
        expect(await screen.findByText('Event published')).toBeInTheDocument();
        expect(formError()).toBeEmptyDOMElement();
      });

      it('becomes the form of a published event when the new host fails after the publication, and says what was saved', async () => {
        update.mockResolvedValueOnce(draftAnswer());
        update.mockResolvedValueOnce(refused(403, 'This action is unauthorized.'));
        update.mockResolvedValueOnce(publishedAnswer());
        setState.mockResolvedValue(publishedAnswer());
        renderForm({ event: draft(), permissions: HOST });
        const user = userEvent.setup();
        fill('Title', 'A better title');
        await nameGrace(user);

        await user.click(screen.getByRole('button', { name: 'Publish' }));

        await waitFor(() =>
          expect(formError()?.textContent).toBe('This action is unauthorized. The event is published. The host did not change.'),
        );
        expect(update).toHaveBeenNthCalledWith(1, 'c1', 's1', 'e1', { title: 'A better title' });
        expect(update).toHaveBeenNthCalledWith(2, 'c1', 's1', 'e1', { host_cuid: 'u2' });
        expect(shared.visit).not.toHaveBeenCalled();
        // The event is published: no "Save as draft" that would change the host of a published event and say "Draft saved"
        expect(screen.queryByRole('button', { name: 'Save as draft' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Publish' })).toBeNull();
        expect(screen.queryByText(/You will not see it any more/)).toBeNull();

        await user.click(screen.getByRole('button', { name: 'Save changes' }));

        // Only the host is left to save, and the state has its one request
        await waitFor(() => expect(update).toHaveBeenNthCalledWith(3, 'c1', 's1', 'e1', { host_cuid: 'u2' }));
        expect(setState).toHaveBeenCalledTimes(1);
        expect(await screen.findByText('Changes saved')).toBeInTheDocument();
        expect(screen.queryByText('Draft saved')).toBeNull();
        // The page of the event, which everyone can read: not the space
        expect(shared.visit).toHaveBeenCalledWith('/community/tests/space/office-hours/event/weekly-review-office-hour');
      });

      it('shows a field error of the new host next to "Host", with what was saved above the buttons', async () => {
        update.mockResolvedValueOnce(draftAnswer());
        update.mockResolvedValueOnce({
          ok: false,
          status: 400,
          message: 'The given data was invalid.',
          errors: { host_cuid: ['Choose a member of the community who can read this space'] },
        });
        setState.mockResolvedValue(publishedAnswer());
        renderForm({ event: draft(), permissions: HOST });
        const user = userEvent.setup();
        await nameGrace(user);

        await user.click(screen.getByRole('button', { name: 'Publish' }));

        expect(await screen.findByText('Choose a member of the community who can read this space')).toHaveAttribute(
          'id',
          'event-host-error',
        );
        expect(formError()?.textContent).toBe('The event is published. The host did not change.');
        expect(screen.getByRole('button', { name: 'Save changes' })).toBeInTheDocument();
      });
    });

    it('names a new host after the publication of a draft: the publication needs the rights of the host', async () => {
      const order: string[] = [];
      update.mockImplementation((_community, _space, _event, input) => {
        order.push(`update ${Object.keys(input).join(',')}`);
        return Promise.resolve(saved());
      });
      setState.mockImplementation(() => {
        order.push('state');
        return Promise.resolve(saved());
      });
      renderForm({ event: draft() });
      const user = userEvent.setup();
      await user.click(screen.getByRole('button', { name: 'Remove Ada Lovelace' }));
      await user.type(screen.getByRole('combobox', { name: 'Host' }), 'gra');
      await user.click(await screen.findByRole('option', { name: /Grace Hopper/ }));

      await user.click(screen.getByRole('button', { name: 'Publish' }));

      await waitFor(() => expect(order).toEqual(['update ', 'state', 'update host_cuid']));
      expect(update).toHaveBeenLastCalledWith('c1', 's1', 'e1', { host_cuid: 'u2' });
    });

    it('keeps the answer to "summer time or winter time?" of the event', () => {
      // 31 October 2027, 02:30 in winter time (UTC+1) is 01:30 UTC
      renderForm({
        event: eventFixture('e1', 'Night', { starts_at: '2027-10-31T01:30:00.000000Z', ends_at: '2027-10-31T03:00:00.000000Z', host: ME }),
      });

      expect(screen.getByRole('radio', { name: 'Winter time (UTC+1)' })).toBeChecked();
      expect(screen.getByRole('radio', { name: 'Summer time (UTC+2)' })).not.toBeChecked();
      expect(summary()).toBe('Sunday 31 October 2027, 02:30 to 04:00, Central European Standard Time (UTC+1). In your zone: the same.');
    });
  });

  describe('the errors (rule 30)', () => {
    it('shows the errors of the form next to their fields, without moving the focus, and sends nothing', async () => {
      renderForm();
      const button = screen.getByRole('button', { name: 'Save as draft' });

      await userEvent.click(button);

      expect(await screen.findByText('Use between 3 and 200 characters')).toHaveAttribute('role', 'alert');
      expect(screen.getByText('Choose a start date')).toHaveAttribute('role', 'alert');
      expect(screen.getByText('Choose a start time')).toHaveAttribute('role', 'alert');
      expect(screen.getByText('Choose an end time')).toHaveAttribute('role', 'alert');
      expect(screen.getByLabelText('Title')).toHaveAttribute('aria-invalid', 'true');
      expect(screen.getByLabelText('Title')).toHaveAttribute('aria-describedby', 'event-title-error');
      expect(button).toHaveFocus();
      expect(formError()).toHaveTextContent(EVENT_WORDS.fixTheFields);
      expect(formError()).toHaveAttribute('role', 'alert');
      expect(create).not.toHaveBeenCalled();
    });

    it('refuses an address that is no http or https address, next to its field', async () => {
      renderForm();
      fillTimes();
      fill('Meeting address', NOT_AN_ADDRESS);

      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));

      expect(await screen.findByText('Use an address that starts with http:// or https://')).toHaveAttribute(
        'id',
        'event-meeting-url-error',
      );
      expect(create).not.toHaveBeenCalled();
    });

    it('shows a field error of the server next to its field: the start under "Start time", the end under "End time"', async () => {
      create.mockResolvedValue({
        ok: false,
        status: 400,
        message: 'The given data was invalid.',
        errors: {
          starts_at: ['Choose a start in the future'],
          ends_at: ['An event lasts 24 hours at most'],
          title: ['The title field must be at least 3 characters.'],
          host_cuid: ['Choose a member of the community who can read this space'],
          cover_upload_cuid: ['Choose an image uploaded in this community'],
        },
      });
      renderForm();
      fillTimes();
      const button = screen.getByRole('button', { name: 'Publish' });

      await userEvent.click(button);

      const start = await screen.findByText('Choose a start in the future');
      expect(start).toHaveAttribute('role', 'alert');
      expect(start).toHaveAttribute('id', 'event-start-time-error');
      expect(screen.getByText('An event lasts 24 hours at most')).toHaveAttribute('id', 'event-end-time-error');
      expect(screen.getByText('The title field must be at least 3 characters.')).toHaveAttribute('id', 'event-title-error');
      expect(screen.getByText('Choose a member of the community who can read this space')).toHaveAttribute('id', 'event-host-error');
      expect(screen.getByText('Choose an image uploaded in this community')).toHaveAttribute('id', 'event-cover-error');
      // The focus stays, and the page stays
      expect(button).toHaveFocus();
      expect(shared.visit).not.toHaveBeenCalled();
      expect(formError()).toBeEmptyDOMElement();
    });

    it('links the error of "Time zone" to its field, which is marked as invalid', async () => {
      create.mockResolvedValue({
        ok: false,
        status: 400,
        message: 'The given data was invalid.',
        errors: { time_zone: ['Choose a time zone of the list'] },
      });
      renderForm();
      fillTimes();
      // A valid field has no error state, and nothing describes it
      expect(zoneField()).not.toHaveAttribute('aria-invalid');
      expect(zoneField()).not.toHaveAttribute('aria-describedby');

      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));

      const error = await screen.findByText('Choose a time zone of the list');
      expect(error).toHaveAttribute('role', 'alert');
      expect(error).toHaveAttribute('id', 'event-time-zone-error');
      expect(zoneField()).toHaveAttribute('aria-invalid', 'true');
      expect(zoneField()).toHaveAttribute('aria-describedby', 'event-time-zone-error');
      expect(zoneField()).toHaveAccessibleDescription('Choose a time zone of the list');
    });

    it('keeps its live regions in the page while they are empty, never with display none: their first text is read', () => {
      renderForm();

      // An empty region that is `display: none` is not in the accessibility tree
      const regions = ['event-when-summary', 'event-host-status', 'event-form-error'].map((slot) =>
        document.querySelector(`[data-slot="${slot}"]`),
      );
      for (const region of regions) {
        expect(region).toBeEmptyDOMElement();
        expect(region).toHaveClass('empty:sr-only');
        expect(region).not.toHaveClass('empty:hidden');
        expect(region).not.toHaveClass('hidden');
      }
      expect(regions.map((region) => region?.getAttribute('role'))).toEqual(['status', 'status', 'alert']);
    });

    it('shows a refused rule of the server (422) above the buttons, with its words', async () => {
      update.mockResolvedValue(saved({ state: 'draft' }));
      setState.mockResolvedValue({ ok: false, status: 422, message: 'An event that started cannot be published again.', errors: {} });
      renderForm({ event: eventFixture('e1', 'Weekly review', { state: 'draft', host: ME }) });

      await userEvent.click(screen.getByRole('button', { name: 'Publish' }));

      await waitFor(() => expect(formError()).toHaveTextContent('An event that started cannot be published again.'));
      expect(formError()).toHaveAttribute('role', 'alert');
      const buttons = screen.getByRole('button', { name: 'Publish' });
      expect((formError() as Element).compareDocumentPosition(buttons) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      expect(shared.visit).not.toHaveBeenCalled();
    });

    it('keeps the focus on the busy button, and sends one request for two clicks', async () => {
      let answer: (value: Awaited<ReturnType<typeof knowiiApiClient.communities.events.create>>) => void = () => undefined;
      create.mockImplementation(
        () =>
          new Promise((resolve) => {
            answer = resolve;
          }),
      );
      renderForm();
      fillTimes();
      const button = screen.getByRole('button', { name: 'Publish' });

      await userEvent.click(button);
      await waitFor(() => expect(button).toHaveAttribute('aria-busy', 'true'));
      await userEvent.click(button);
      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));

      expect(create).toHaveBeenCalledTimes(1);
      expect(button).not.toBeDisabled();
      expect(document.activeElement).not.toBe(document.body);
      await act(async () => answer(saved()));
      await waitFor(() => expect(shared.visit).toHaveBeenCalledTimes(1));
    });
  });

  describe('the three fields of the answers (#1515, rules 45 to 54)', () => {
    const published = (changes: Record<string, unknown> = {}) =>
      eventFixture('e1', 'Weekly review', { body: 'Bring questions', host: ME, ...changes });
    const visibility = () => screen.getByRole('group', { name: 'Who sees the meeting address and the place' });
    const limit = () => screen.getByLabelText<HTMLInputElement>('Limit of places');
    const replay = () => screen.getByRole<HTMLInputElement>('checkbox', { name: 'A replay will exist' });

    it('puts them after "Place" and before "Host", in their order (rule 45)', () => {
      renderForm();

      const order = [screen.getByLabelText('Place'), visibility(), limit(), replay(), screen.getByRole('combobox', { name: 'Host' })];
      for (let index = 1; index < order.length; index++) {
        expect(
          (order[index - 1] as HTMLElement).compareDocumentPosition(order[index] as HTMLElement) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
      }
    });

    it('asks who sees the meeting address in a fieldset with two radios, and starts a new event with "Every member" (rule 46)', () => {
      renderForm();

      expect(visibility().tagName).toBe('FIELDSET');
      expect(visibility().querySelector('legend')).toHaveTextContent('Who sees the meeting address and the place');
      const radios = within(visibility()).getAllByRole('radio');
      expect(radios.map((radio) => radio.closest('label')?.textContent)).toEqual(['Every member', 'Members who answer Yes']);
      expect(within(visibility()).getByRole('radio', { name: 'Every member' })).toBeChecked();
      expect(screen.queryByText('The host, the owner and the admins always see them.')).toBeNull();
    });

    it('starts an edit with the stored choice, and says who always sees them with "Members who answer Yes" (rule 46)', async () => {
      renderForm({ event: published({ location_visibility: 'going' }) });

      expect(within(visibility()).getByRole('radio', { name: 'Members who answer Yes' })).toBeChecked();
      const hint = screen.getByText('The host, the owner and the admins always see them.');
      // A polite status that describes the group: it is read when it comes, and when the focus enters the group
      expect(hint).toHaveAttribute('role', 'status');
      expect(visibility()).toHaveAccessibleDescription('The host, the owner and the admins always see them.');

      await userEvent.click(within(visibility()).getByRole('radio', { name: 'Every member' }));
      expect(screen.queryByText('The host, the owner and the admins always see them.')).toBeNull();
      // The region stays in the page, empty
      expect(hint).toBeInTheDocument();
      expect(hint).toBeEmptyDOMElement();
    });

    it('moves the choice with the arrow keys: one tab stop for the two radios (rule 46)', async () => {
      renderForm();

      within(visibility()).getByRole('radio', { name: 'Every member' }).focus();
      await userEvent.keyboard('{ArrowDown}');

      expect(within(visibility()).getByRole('radio', { name: 'Members who answer Yes' })).toBeChecked();
      expect(within(visibility()).getByRole('radio', { name: 'Members who answer Yes' })).toHaveFocus();
      await userEvent.tab();
      expect(limit()).toHaveFocus();
    });

    it('writes the hints of "Meeting address" and "Place" for the choice (rule 47)', async () => {
      renderForm();
      const address = screen.getByLabelText('Meeting address');
      const place = screen.getByLabelText('Place');
      expect(address).toHaveAccessibleDescription('An address that starts with https://. Only members see it.');
      expect(place).toHaveAccessibleDescription('For an event in a room. Only members see it.');

      await userEvent.click(within(visibility()).getByRole('radio', { name: 'Members who answer Yes' }));

      expect(address).toHaveAccessibleDescription('An address that starts with https://. Only members who answer Yes see it.');
      expect(place).toHaveAccessibleDescription('For an event in a room. Only members who answer Yes see it.');
    });

    it('has a field "Limit of places" with its label and its hint, empty for a new event (rule 48)', () => {
      renderForm();

      expect(limit()).toHaveValue('');
      expect(limit()).toHaveAttribute('inputmode', 'numeric');
      expect(limit()).toHaveAccessibleDescription('Leave empty for no limit. Only the Yes answers take a place.');
      expect(screen.getByText('Limit of places').tagName).toBe('LABEL');
    });

    it.each(['0', '5001', '1.5', 'abc'])('refuses the limit "%s" next to its field, and sends nothing (rule 48)', async (value) => {
      renderForm();
      fillTimes();
      fill('Limit of places', value);

      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));

      const error = await screen.findByText('Use a whole number from 1 to 5000, or leave the field empty');
      expect(error).toHaveAttribute('role', 'alert');
      expect(limit()).toHaveAttribute('aria-invalid', 'true');
      expect(limit().getAttribute('aria-describedby')).toContain(error.id);
      expect(create).not.toHaveBeenCalled();
      expect(formError()).toHaveTextContent(EVENT_WORDS.fixTheFields);
    });

    it.each([
      ['1', 1],
      ['5000', 5000],
      [' 40 ', 40],
    ])('accepts the limit "%s" and sends it as a number (rule 48)', async (value, sent) => {
      renderForm();
      fillTimes();
      fill('Limit of places', value);

      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));

      await waitFor(() => expect(create).toHaveBeenCalledTimes(1));
      expect(create.mock.calls[0]?.[2]).toMatchObject({ capacity: sent });
    });

    it('sends no request for the Yes answers of a new event and of a draft, and shows no line (rule 49)', async () => {
      const { unmount } = renderForm();
      expect(answersList).not.toHaveBeenCalled();
      expect(capacityStatus()).toBeEmptyDOMElement();
      unmount();

      renderForm({ event: published({ state: 'draft', can_answer: false }) });
      await act(async () => undefined);
      expect(answersList).not.toHaveBeenCalled();
      expect(capacityStatus()).toBeEmptyDOMElement();
    });

    it.each([
      [0, ''],
      [1, '1 member answered Yes.'],
      [12, '12 members answered Yes.'],
    ])('reads the number of Yes answers of a published event one time, and says it under the limit: %i (rule 49)', async (yes, words) => {
      answersList.mockResolvedValue(yesAnswers(yes));
      renderForm({ event: published({ capacity: 40 }) });

      await waitFor(() => expect(answersList).toHaveBeenCalledTimes(1));
      expect(answersList).toHaveBeenCalledWith('c1', 's1', 'e1', 'yes', 1, 0);
      await waitFor(() => expect(capacityStatus().textContent).toBe(words));
      // A polite status that is in the page from the start, and a part of the description of the field when it says something
      expect(capacityStatus()).toHaveAttribute('role', 'status');
      expect(limit()).toHaveAccessibleDescription(
        `Leave empty for no limit. Only the Yes answers take a place.${words === '' ? '' : ` ${words}`}`,
      );

      // A change of the form sends no second request
      fill('Title', 'A better title');
      fill('Limit of places', '50');
      expect(answersList).toHaveBeenCalledTimes(1);
    });

    it('reads the Yes answers of a cancelled event too (rule 49)', async () => {
      answersList.mockResolvedValue(yesAnswers(3));
      renderForm({ event: published({ state: 'cancelled', can_answer: false }) });

      await waitFor(() => expect(capacityStatus()).toHaveTextContent('3 members answered Yes.'));
    });

    it('shows no line and no error when the number cannot be read (rule 49)', async () => {
      answersList.mockResolvedValue({ ok: false, status: 500, message: 'Failed to load the answers.', errors: {} });
      renderForm({ event: published({ capacity: 40 }) });

      await waitFor(() => expect(answersList).toHaveBeenCalledTimes(1));
      await act(async () => undefined);
      expect(capacityStatus()).toBeEmptyDOMElement();
      expect(screen.queryByText('Failed to load the answers.')).toBeNull();
      expect(screen.queryByRole('alert', { name: /answers/ })).toBeNull();
      expect(formError()).toBeEmptyDOMElement();
    });

    it('says what a limit under the number of Yes answers means, as a hint, and saves it (rule 50)', async () => {
      answersList.mockResolvedValue(yesAnswers(12));
      renderForm({ event: published({ capacity: 40 }) });
      await waitFor(() => expect(capacityStatus()).toHaveTextContent('12 members answered Yes.'));

      // While the person types, the line waits: it changes when the field is left
      fill('Limit of places', '10', false);
      expect(capacityStatus().textContent).toBe('12 members answered Yes.');
      fireEvent.blur(limit());

      expect(capacityStatus().textContent).toBe('12 members answered Yes. Nobody is removed. No other member can answer Yes.');
      // A hint in the polite status, not an error
      expect(limit()).not.toHaveAttribute('aria-invalid', 'true');
      expect(screen.queryByText('Use a whole number from 1 to 5000, or leave the field empty')).toBeNull();

      await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));
      await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', { capacity: 10 }));
    });

    it.each([
      ['the same number', '12'],
      ['a larger number', '13'],
      ['no limit', ''],
      ['a text that is no limit', 'abc'],
    ])('keeps the plain line for %s (rule 50)', async (_name, value) => {
      answersList.mockResolvedValue(yesAnswers(12));
      renderForm({ event: published({ capacity: 10 }) });
      // The stored limit is under the number of Yes answers: the form says so when it opens
      await waitFor(() => expect(capacityStatus()).toHaveTextContent('Nobody is removed.'));

      fill('Limit of places', value);

      expect(capacityStatus().textContent).toBe('12 members answered Yes.');
    });

    it('has the checkbox "A replay will exist" with its hint, not ticked for a new event (rule 51)', async () => {
      renderForm();

      expect(replay()).not.toBeChecked();
      expect(replay()).toHaveAccessibleDescription('Members can then answer Replay only.');

      await userEvent.click(screen.getByText('A replay will exist'));
      expect(replay()).toBeChecked();
    });

    it('sends the three fields with a new event (rule 52)', async () => {
      renderForm();
      fillTimes();
      await userEvent.click(within(visibility()).getByRole('radio', { name: 'Members who answer Yes' }));
      fill('Limit of places', '40');
      await userEvent.click(replay());

      await userEvent.click(screen.getByRole('button', { name: 'Publish' }));

      await waitFor(() => expect(create).toHaveBeenCalledTimes(1));
      expect(create.mock.calls[0]?.[2]).toMatchObject({
        location_visibility: 'going',
        capacity: 40,
        replay_expected: true,
        state: 'published',
      });
    });

    it.each([
      ['who sees the meeting address', { location_visibility: 'going' }],
      ['the limit', { capacity: 25 }],
      ['the replay choice', { replay_expected: true }],
    ] as const)('sends only %s when it is the one that changed (rule 52)', async (_name, change) => {
      renderForm({ event: published({ capacity: 40 }) });

      if ('location_visibility' in change) {
        await userEvent.click(within(visibility()).getByRole('radio', { name: 'Members who answer Yes' }));
      } else if ('capacity' in change) {
        fill('Limit of places', '25');
      } else {
        await userEvent.click(replay());
      }
      await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));

      await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', change));
    });

    it('sends an empty limit as null, and nothing when the limit was already empty (rule 52)', async () => {
      const { unmount } = renderForm({ event: published({ capacity: 40, location_visibility: 'going', replay_expected: true }) });
      expect(limit()).toHaveValue('40');
      fill('Limit of places', '');

      await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));

      await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', { capacity: null }));
      unmount();
      update.mockClear();

      renderForm({ event: published({ capacity: null }) });
      await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));
      await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', {}));
    });

    it('shows a field error of the server next to each of the three fields (rule 53)', async () => {
      update.mockResolvedValue({
        ok: false,
        status: 400,
        message: 'The provided data is invalid',
        errors: {
          capacity: ['The capacity field must not be greater than 5000.'],
          location_visibility: ['The selected location visibility is invalid.'],
          replay_expected: ['The replay expected field must be true or false.'],
        },
      });
      renderForm({ event: published() });
      fill('Limit of places', '50');

      await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));

      const capacityError = await screen.findByText('The capacity field must not be greater than 5000.');
      expect(limit().getAttribute('aria-describedby')).toContain(capacityError.id);
      expect(limit()).toHaveAttribute('aria-invalid', 'true');
      expect(within(visibility()).getByText('The selected location visibility is invalid.')).toHaveAttribute('role', 'alert');
      const replayError = screen.getByText('The replay expected field must be true or false.');
      expect(replay().getAttribute('aria-describedby')).toContain(replayError.id);
      // Each error is next to its field: nothing above the buttons
      expect(formError()).toBeEmptyDOMElement();
      expect(shared.visit).not.toHaveBeenCalled();
    });

    it('shows the limit and the replay choice of a copy: the server copied them (rule 54)', () => {
      // The form of the draft that "Duplicate" made
      renderForm({
        event: published({ state: 'draft', can_answer: false, capacity: 40, replay_expected: true, location_visibility: 'going' }),
      });

      expect(limit()).toHaveValue('40');
      expect(replay()).toBeChecked();
      expect(within(visibility()).getByRole('radio', { name: 'Members who answer Yes' })).toBeChecked();
    });

    it('listens to no live signal: nothing changes what the host typed (rule 58)', async () => {
      const { emitSocketEvent } = await import('@/test-utils/fake-echo');
      answersList.mockResolvedValue(yesAnswers(12));
      renderForm({ event: published({ capacity: 40 }) });
      await waitFor(() => expect(capacityStatus()).toHaveTextContent('12 members answered Yes.'));
      fill('Limit of places', '30');

      act(() => emitSocketEvent('community.c1.space.s1', 'space.event.answers.changed', { space_cuid: 's1', event_cuid: 'e1' }));
      act(() => emitSocketEvent('community.c1.space.s1', 'space.event.updated', { space_cuid: 's1', event_cuid: 'e1' }));

      expect(limit()).toHaveValue('30');
      expect(answersList).toHaveBeenCalledTimes(1);
    });
  });

  describe('after the event (#1516, design 2.4)', () => {
    const ended = (changes: Record<string, unknown> = {}) =>
      eventFixture('e1', 'Weekly review', {
        host: ME,
        is_past: true,
        starts_at: '2026-11-04T16:00:00.000000Z',
        ends_at: '2026-11-04T17:00:00.000000Z',
        ...changes,
      });
    const group = () => screen.queryByRole('group', { name: 'After the event' });

    it('has the group "After the event" only on a published event that ended, after "A replay will exist" and before "Host"', () => {
      for (const event of [
        null,
        eventFixture('e1', 'Weekly review', { host: ME }),
        ended({ state: 'draft' }),
        ended({ state: 'cancelled' }),
      ]) {
        const { unmount } = renderForm({ event });
        expect(group()).toBeNull();
        expect(screen.queryByLabelText('Replay link')).toBeNull();
        unmount();
      }

      renderForm({ event: ended() });
      const fieldset = group() as HTMLElement;
      const replay = screen.getByRole('checkbox', { name: 'A replay will exist' });
      const host = screen.getByRole('combobox', { name: 'Host' });
      expect(replay.compareDocumentPosition(fieldset) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      expect(fieldset.compareDocumentPosition(host) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      expect(fieldset).toHaveAttribute('id', 'event-after');
    });

    it('describes "Replay link" with the approved sentence about the agreement, then who sees it, and "Notes" with who reads them', () => {
      renderForm({ event: ended() });

      const replay = screen.getByLabelText('Replay link');
      expect(replay).toHaveAttribute('type', 'url');
      expect(replay).toHaveAccessibleDescription(
        'Before you add a recording, make sure the people in it agreed. Members who can see the event see it. Visitors do not.',
      );
      expect(screen.getByLabelText('Notes')).toHaveAccessibleDescription('Everyone who can read the event reads the notes.');
    });

    it('sends only the replay link and the notes that changed, and an empty field clears its value', async () => {
      update.mockResolvedValue(saved({ is_past: true, replay_url: 'https://example.com/replay' }));
      renderForm({ event: ended({ notes: 'The slides' }) });

      fill('Replay link', ' https://example.com/replay ');
      fill('Notes', '');
      await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));

      await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 's1', 'e1', { replay_url: 'https://example.com/replay', notes: '' }));
    });

    it('shows a field error of the server under its field, and the focus stays on the button (axe)', async () => {
      update.mockResolvedValue({
        ok: false,
        status: 400,
        message: 'The given data was invalid.',
        errors: {
          replay_url: ['Add the replay once the event is published and over.'],
          notes: ['Add the notes once the event is published and over.'],
        },
      });
      const { container } = renderForm({ event: ended() });
      fill('Replay link', 'https://example.com/replay');
      fill('Notes', 'The slides');
      const button = screen.getByRole('button', { name: 'Save changes' });

      await userEvent.click(button);

      const error = await screen.findByText('Add the replay once the event is published and over.');
      expect(error).toHaveAttribute('role', 'alert');
      expect(error).toHaveAttribute('id', 'event-replay-url-error');
      expect(screen.getByText('Add the notes once the event is published and over.')).toHaveAttribute('id', 'event-notes-error');
      expect(button).toHaveFocus();
      expect(shared.visit).not.toHaveBeenCalled();
      const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });

    it('refuses an address that is not http or https before it sends anything', async () => {
      renderForm({ event: ended() });

      fill('Replay link', NOT_AN_ADDRESS);
      await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));

      expect(await screen.findByText('Use an address that starts with http:// or https://')).toBeInTheDocument();
      expect(update).not.toHaveBeenCalled();
    });
  });

  describe('accessibility (design 11.6)', () => {
    const rules = { 'color-contrast': { enabled: false }, region: { enabled: false } };

    it('has no automated violation with the three fields of the answers: a limit under the Yes answers, and a wrong limit (axe)', async () => {
      answersList.mockResolvedValue(yesAnswers(12));
      const { container } = renderForm({
        event: eventFixture('e1', 'Weekly review', { host: ME, capacity: 10, replay_expected: true, location_visibility: 'going' }),
        permissions: OWNER,
      });
      await waitFor(() => expect(capacityStatus()).toHaveTextContent('Nobody is removed.'));

      expect((await axe.run(container, { rules })).violations.map((violation) => violation.id)).toEqual([]);

      fill('Limit of places', '0');
      await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));
      await screen.findByText('Use a whole number from 1 to 5000, or leave the field empty');

      expect((await axe.run(container, { rules })).violations.map((violation) => violation.id)).toEqual([]);
    });

    it('has no automated violation for a new event, for a moderator (axe)', async () => {
      const { container } = renderForm({ permissions: MODERATOR });
      fillTimes('2026-11-18', '23:00', '01:00');

      const results = await axe.run(container, { rules });

      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });

    it('has no automated violation with its errors and the question about the clocks (axe)', async () => {
      const { container } = renderForm();
      fill('Start date', '2027-10-31');
      fill('Start time', '02:30');
      await userEvent.click(screen.getByRole('button', { name: 'Save as draft' }));
      await screen.findByText('Choose an end time');

      const results = await axe.run(container, { rules });

      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });

    it('has no automated violation for a published event with a cover (axe)', async () => {
      const cover = {
        cuid: 'up0',
        url: '/uploads/old.webp',
        mime_type: 'image/webp',
        width: 1600,
        height: 900,
        size_bytes: 1000,
        alt: 'x',
      };
      const { container } = renderForm({
        event: eventFixture('e1', 'Weekly review', { cover, cover_alt: 'An old picture', host: ME }),
        permissions: OWNER,
      });

      const results = await axe.run(container, { rules });

      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });
  });
});
