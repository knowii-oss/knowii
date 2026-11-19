import type { MockInstance } from 'vitest';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import { knowiiApiClient } from '@knowii/common';
import { TimeZoneForm } from './TimeZoneForm';

const shared = vi.hoisted(() => ({ reload: vi.fn() }));

vi.mock('@inertiajs/react', () => ({ router: { reload: shared.reload } }));

// The offsets are the ones of the day of the test: Brussels and New York have two, Tokyo has one
const BRUSSELS = /^Brussels, Europe \(UTC\+[12]\)/;
const NEW_YORK = /^New York, America \(UTC-[45]\)/;
const TOKYO = 'Tokyo, Asia (UTC+9)';

/**
 * The zone that the browser reports, for the pinned entries
 */
function browserIn(zone: string): void {
  const resolvedOptions = Intl.DateTimeFormat.prototype.resolvedOptions;
  vi.spyOn(Intl.DateTimeFormat.prototype, 'resolvedOptions').mockImplementation(function (this: Intl.DateTimeFormat) {
    return { ...resolvedOptions.call(this), timeZone: zone };
  });
}

// Each key renders the list again, with up to 50 zones: seconds on a busy machine
describe('the time zone picker of the settings page (#1666)', { timeout: 30_000 }, () => {
  const onStatus = vi.fn();
  let save: MockInstance<typeof knowiiApiClient.users.setTimeZone>;

  beforeEach(() => {
    onStatus.mockClear();
    shared.reload.mockClear();
    browserIn('America/New_York');
    save = vi
      .spyOn(knowiiApiClient.users, 'setTimeZone')
      .mockImplementation((zone) => Promise.resolve({ ok: true, message: '', data: { time_zone: zone } }));
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  const renderForm = (timeZone: string | null = 'Europe/Brussels') =>
    render(
      <main>
        <TimeZoneForm timeZone={timeZone} onStatus={onStatus} />
      </main>,
    );
  const field = () => screen.getByRole<HTMLInputElement>('combobox', { name: 'Time zone' });
  const optionTexts = async () =>
    within(await screen.findByRole('listbox', { name: 'Time zone' }))
      .getAllByRole('option')
      .map((option) => option.textContent);

  /**
   * Puts a search text in the field, in place of what it holds, and gives the entries of the list. The text comes in
   * one step (a paste): the test of the keys types it key by key.
   */
  async function search(user: ReturnType<typeof userEvent.setup>, text: string): Promise<(string | null)[]> {
    await user.clear(field());
    await user.paste(text);
    await waitFor(() => expect(field()).toHaveValue(text));
    return optionTexts();
  }

  it('shows the zone of the account as "city, region (offset)" in the closed field', () => {
    renderForm();

    expect(field().value).toMatch(BRUSSELS);
    expect(field()).toHaveAttribute('aria-expanded', 'false');
    expect(field()).toHaveAttribute('id', 'time-zone-select');
    expect(screen.getByText('Time zone', { selector: 'label' })).toHaveAttribute('for', 'time-zone-select');
    expect(screen.queryByRole('listbox')).toBeNull();
  });

  it('keeps only the zones that match, at each key, and says the number of results', async () => {
    renderForm();
    const user = userEvent.setup();

    await user.click(field());
    await user.keyboard('b');
    const first = await optionTexts();
    expect(first.length).toBeGreaterThan(10);
    first.forEach((text) => expect(text).toMatch(/b/i));

    await user.keyboard('rus');
    await waitFor(async () => expect(await optionTexts()).toHaveLength(1));
    expect((await optionTexts())[0]).toMatch(BRUSSELS);
    expect(screen.getByRole('status')).toHaveTextContent('1 result');
    expect(screen.getByRole('status')).toHaveAttribute('aria-live', 'polite');
  });

  it('finds a zone by its city alone, whatever the case, with the long name of the zone under it', async () => {
    renderForm();
    const user = userEvent.setup();

    expect(await search(user, 'TOKYO')).toEqual([`${TOKYO} Japan Standard Time`]);
  });

  it('takes spaces for underscores and for slashes, and finds the identifier too', async () => {
    renderForm();
    const user = userEvent.setup();

    const withSpace = await search(user, 'new york');
    expect(withSpace).toHaveLength(1);
    expect(withSpace[0]).toMatch(NEW_YORK);
    expect((await search(user, 'America/New_York'))[0]).toMatch(NEW_YORK);
    expect((await search(user, 'america new_y'))[0]).toMatch(NEW_YORK);
    // The region and the city, in the order of the identifier or of the label
    expect((await search(user, 'europe brus'))[0]).toMatch(BRUSSELS);
    expect((await search(user, 'brussels, eur'))[0]).toMatch(BRUSSELS);
    // An accent in the search text does not count
    expect((await search(user, 'são paulo'))[0]).toMatch(/^Sao Paulo, America/);
  });

  it('puts the zone UTC first for "utc", before the zones that only hold it in their offset', async () => {
    renderForm();
    const user = userEvent.setup();

    expect((await search(user, 'utc'))[0]).toBe('UTC Coordinated Universal Time');
  });

  it('says "No results" when no zone matches, in the list and in the status', async () => {
    renderForm();
    const user = userEvent.setup();

    await user.click(field());
    await user.paste('atlantis');

    const listbox = await screen.findByRole('listbox', { name: 'Time zone' });
    expect(within(listbox).queryAllByRole('option')).toEqual([]);
    expect(within(listbox).getByText('No results')).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('No results');
  });

  it('puts the zone of the browser and the zone of the account at the top without a search text, then the others', async () => {
    renderForm();
    const user = userEvent.setup();

    await user.click(screen.getByRole('button', { name: 'Show the list Time zone' }));

    const texts = await optionTexts();
    expect(texts[0]).toMatch(NEW_YORK);
    expect(texts[1]).toMatch(BRUSSELS);
    expect(texts[2]).toBe('Abidjan, Africa (UTC+0) Greenwich Mean Time');
    // Each zone is in the list one time
    expect(texts.filter((text) => text !== null && NEW_YORK.test(text))).toHaveLength(1);
    expect(texts.filter((text) => text !== null && BRUSSELS.test(text))).toHaveLength(1);
    const listbox = screen.getByRole('listbox', { name: 'Time zone' });
    expect(within(listbox).getByRole('option', { name: BRUSSELS })).toHaveAttribute('aria-selected', 'true');
  });

  it('opens the list on a click in the field, and not when the Tab key gives it the focus', async () => {
    renderForm();
    const user = userEvent.setup();

    await user.tab();
    expect(field()).toHaveFocus();
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();

    await user.click(field());

    const texts = await optionTexts();
    expect(texts[0]).toMatch(NEW_YORK);
    expect(texts[1]).toMatch(BRUSSELS);
    // The field keeps the focus, with its label selected: typing starts a search
    expect(field()).toHaveFocus();
  });

  it('puts the zone of the browser alone at the top when it is the zone of the account, or when the account has none', async () => {
    const { unmount } = renderForm('America/New_York');
    const user = userEvent.setup();
    await user.click(screen.getByRole('button', { name: 'Show the list Time zone' }));
    let texts = await optionTexts();
    expect(texts[0]).toMatch(NEW_YORK);
    expect(texts[1]).toBe('Abidjan, Africa (UTC+0) Greenwich Mean Time');
    unmount();

    renderForm(null);
    expect(field()).toHaveValue('');
    expect(field()).toHaveAttribute('placeholder', 'Choose a time zone');
    await user.click(screen.getByRole('button', { name: 'Show the list Time zone' }));
    texts = await optionTexts();
    expect(texts[0]).toMatch(NEW_YORK);
    expect(texts[1]).toBe('Abidjan, Africa (UTC+0) Greenwich Mean Time');
  });

  it('renders 50 zones at most and says how to see the others', async () => {
    renderForm();
    const user = userEvent.setup();

    await user.click(field());
    await user.keyboard('{ArrowDown}');

    expect(await optionTexts()).toHaveLength(50);
    expect(within(screen.getByRole('listbox', { name: 'Time zone' })).getByText('Type to see more')).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent(/^\d{3} results\. Type to see more\.$/);
  });

  it('picks with the arrows and Enter, saves at once with the identifier, and says "Saved"', async () => {
    renderForm();
    const user = userEvent.setup();

    await user.click(field());
    await user.keyboard('tok');
    // The name of an option is its label and its description: compare its start as exact text, without a pattern
    await screen.findByRole('option', { name: (name) => name.startsWith(TOKYO) });
    await user.keyboard('{Enter}');

    expect(save).toHaveBeenCalledExactlyOnceWith('Asia/Tokyo');
    await waitFor(() => expect(onStatus).toHaveBeenCalledExactlyOnceWith('Saved'));
    expect(field()).toHaveValue(TOKYO);
    expect(field()).toHaveFocus();
    await waitFor(() => expect(screen.queryByRole('listbox')).toBeNull());
    expect(screen.getByText(/Times show in Japan Standard Time \(Asia\/Tokyo\)\./)).toBeInTheDocument();
    expect(shared.reload).toHaveBeenCalledWith({ only: ['auth'] });
  });

  it('picks with a click on a zone, and saves at once', async () => {
    renderForm();
    const user = userEvent.setup();

    await user.click(screen.getByRole('button', { name: 'Show the list Time zone' }));
    await user.click(await screen.findByRole('option', { name: NEW_YORK }));

    expect(save).toHaveBeenCalledExactlyOnceWith('America/New_York');
    await waitFor(() => expect(field().value).toMatch(NEW_YORK));
  });

  it('closes with Escape and keeps the old zone: nothing is saved', async () => {
    renderForm();
    const user = userEvent.setup();

    await user.click(field());
    await user.paste('tok');
    await screen.findByRole('listbox', { name: 'Time zone' });
    await user.keyboard('{Escape}');

    await waitFor(() => expect(screen.queryByRole('listbox')).toBeNull());
    expect(field().value).toMatch(BRUSSELS);
    expect(field()).toHaveFocus();
    expect(save).not.toHaveBeenCalled();
    expect(onStatus).not.toHaveBeenCalled();
  });

  it('keeps the old zone when Tab leaves the open list, and after a click outside', async () => {
    renderForm();
    const user = userEvent.setup();

    await user.click(field());
    await user.paste('tok');
    await screen.findByRole('listbox', { name: 'Time zone' });
    await user.tab();
    await waitFor(() => expect(screen.queryByRole('listbox')).toBeNull());
    expect(field().value).toMatch(BRUSSELS);

    await user.click(field());
    await user.paste('tok');
    await screen.findByRole('listbox', { name: 'Time zone' });
    await user.click(screen.getByText(/Times show in/));
    await waitFor(() => expect(screen.queryByRole('listbox')).toBeNull());
    expect(field().value).toMatch(BRUSSELS);
    expect(save).not.toHaveBeenCalled();
  });

  it('keeps the old zone on an error, with "Not saved. Retry." and the focus on the field', async () => {
    save.mockResolvedValue({ ok: false, status: 422, message: 'Failed', errors: {} });
    renderForm();
    const user = userEvent.setup();

    await user.click(field());
    await user.keyboard('tok{Enter}');

    expect(await screen.findByRole('alert')).toHaveTextContent('Not saved. Retry.');
    expect(field().value).toMatch(BRUSSELS);
    expect(field()).toHaveFocus();
    expect(onStatus).not.toHaveBeenCalled();
    expect(shared.reload).not.toHaveBeenCalled();
  });

  it('has a field of 44 px, and no automated violation (axe) with the list closed, open, and without a result', async () => {
    renderForm();
    const user = userEvent.setup();
    const violations = async () =>
      (await axe.run(document.body, { rules: { 'color-contrast': { enabled: false } } })).violations.map(
        (violation) => `${violation.id}: ${violation.nodes.map((node) => node.html).join(' | ')}`,
      );

    expect(field()).toHaveClass('h-11');
    expect(await violations()).toEqual([]);

    await user.click(field());
    await user.paste('new');
    await screen.findByRole('listbox', { name: 'Time zone' });
    expect(await violations()).toEqual([]);
    // The list is in the landmark of the field, not on the body
    expect(screen.getByRole('listbox', { name: 'Time zone' }).closest('main')).toBe(field().closest('main'));

    await user.paste('xyz');
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('No results'));
    expect(await violations()).toEqual([]);
  });
});
