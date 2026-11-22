import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { AccessGroup, InviteLink, InviteLinkUse, MemberTag } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { AccountTimeZoneProvider, Toaster } from '@/Components/ui';
import { INVITE_LINK_WORDS } from '@/Components/InviteLinks/invite-link-words';
import { InviteLinksManager, joinedText } from './InviteLinksManager';

const tag: MemberTag = {
  cuid: 't1',
  name: 'Buyer',
  color: 'teal',
  visibility: 'members',
  members_count: 2,
  created_at: '2026-11-22T10:00:00.000000Z',
};

const group = (cuid: string, name: string, keys: string[] = []): AccessGroup => ({
  cuid,
  name,
  description: '',
  entitlement_keys: keys,
  created_at: '2026-11-22T10:00:00.000000Z',
});

const link = (cuid: string, name: string, changes: Partial<InviteLink> = {}): InviteLink => ({
  cuid,
  name,
  url: `https://app.example.com/invite/${cuid.padEnd(40, 'x')}`,
  status: 'active',
  active: true,
  expires_at: null,
  max_uses: null,
  uses_count: 0,
  access_groups: [],
  member_tags: [],
  created_at: '2026-11-23T10:00:00.000000Z',
  updated_at: '2026-11-23T10:00:00.000000Z',
  ...changes,
});

const buyers = link('l1', 'Course buyers', {
  expires_at: '2026-11-30T09:00:00.000000Z',
  max_uses: 100,
  uses_count: 3,
  access_groups: [
    { cuid: 'g1', name: 'Cohort', fed_by_rule: false },
    { cuid: 'g2', name: 'Paid', fed_by_rule: true },
  ],
  member_tags: [tag],
});
const off = link('l2', 'Newsletter', { status: 'off', active: false, uses_count: 5 });
const ended = link('l3', 'Webinar', { status: 'ended', expires_at: '2026-11-20T09:00:00.000000Z' });
const full = link('l4', 'Beta', { status: 'full', max_uses: 2, uses_count: 2 });

const use = (cuid: string, name: string): InviteLinkUse => ({
  member: { cuid, name, username: cuid, avatar_url: null },
  joined_at: '2026-11-23T09:00:00.000000Z',
});

function renderManager() {
  return render(
    <AppDataProvider>
      <AccountTimeZoneProvider value="Europe/Brussels">
        <InviteLinksManager communityCuid="c1" permissions={{ canManageAccessGroupMembers: true, canAssignMemberTags: true }} />
      </AccountTimeZoneProvider>
      <Toaster />
    </AppDataProvider>,
  );
}

async function rowOf(name: string): Promise<HTMLElement> {
  const list = await screen.findByRole('list', { name: INVITE_LINK_WORDS.listLabel });
  const row = within(list)
    .getAllByRole('listitem')
    .find((item) => item.parentElement === list && within(item).queryByText(name) !== null);
  if (!row) {
    throw new Error(`No row ${name}`);
  }
  return row;
}

beforeEach(() => {
  vi.restoreAllMocks();
  vi.spyOn(knowiiApiClient.communities.inviteLinks, 'list').mockResolvedValue({ ok: true, message: '', data: [buyers, off, ended, full] });
  vi.spyOn(knowiiApiClient.communities.accessGroups, 'list').mockResolvedValue({
    ok: true,
    message: '',
    data: [group('g1', 'Cohort'), group('g2', 'Paid', ['example.paid'])],
  });
  vi.spyOn(knowiiApiClient.communities.memberTags, 'list').mockResolvedValue({ ok: true, message: '', data: [tag] });
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('the words of a row (#1391)', () => {
  it('says how many joined, and of how many when there is a maximum', () => {
    expect(joinedText({ uses_count: 3, max_uses: null })).toBe('3 joined');
    expect(joinedText({ uses_count: 3, max_uses: 100 })).toBe('3 of 100 joined');
  });
});

describe('InviteLinksManager (#1391)', () => {
  it('lists the links with their state in words, their count, their end and what they give', async () => {
    renderManager();

    const row = await rowOf('Course buyers');
    expect(row).toHaveTextContent('Active');
    expect(row).toHaveTextContent('3 of 100 joined');
    expect(row.querySelector('time')).toHaveAttribute('datetime', '2026-11-30T09:00:00.000Z');
    expect(row).toHaveTextContent('Ends');
    expect(row).toHaveTextContent('Gives');
    expect(row).toHaveTextContent('Cohort');
    expect(row).toHaveTextContent('Buyer');
    expect(row).toHaveTextContent('Paid: a rule on an entitlement feeds this group, so this link does not give it.');
    expect(await rowOf('Newsletter')).toHaveTextContent('Off');
    expect(await rowOf('Newsletter')).toHaveTextContent('5 joined');
    expect(await rowOf('Webinar')).toHaveTextContent('Ended');
    expect(await rowOf('Beta')).toHaveTextContent('Full');
    expect(await rowOf('Beta')).toHaveTextContent('2 of 2 joined');
  });

  it('copies the link, and gives the address in the toast when the copy fails', async () => {
    const writeText = vi.fn().mockResolvedValueOnce(undefined).mockRejectedValueOnce(new Error('denied'));
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
    renderManager();

    const row = await rowOf('Course buyers');
    await userEvent.click(within(row).getByRole('button', { name: 'Copy the link Course buyers' }));
    expect(writeText).toHaveBeenCalledWith(buyers.url);
    expect(await screen.findByText('Link copied')).toBeInTheDocument();

    await userEvent.click(within(row).getByRole('button', { name: 'Copy the link Course buyers' }));
    expect(await screen.findByText('Could not copy the link')).toBeInTheDocument();
    expect(screen.getByText(buyers.url)).toBeInTheDocument();
  });

  it('turns a link off at once, and puts the old state back with an alert on an error', async () => {
    const update = vi
      .spyOn(knowiiApiClient.communities.inviteLinks, 'update')
      .mockResolvedValueOnce({ ok: true, message: '', data: { ...buyers, active: false, status: 'off' } })
      .mockResolvedValueOnce({ ok: false, status: 500, message: 'Failed to save the invite link.', errors: {} });
    renderManager();

    const row = await rowOf('Course buyers');
    const toggle = within(row).getByRole('switch', { name: 'The link Course buyers: On' });
    expect(toggle).toHaveAttribute('aria-checked', 'true');

    await userEvent.click(toggle);
    expect(update).toHaveBeenCalledWith('c1', 'l1', { active: false });
    await waitFor(() => expect(within(row).getByRole('status')).toHaveTextContent('Saved: off'));
    expect(within(row).getByRole('switch', { name: 'The link Course buyers: On' })).toHaveAttribute('aria-checked', 'false');
    expect(within(row).getByText('Off')).toBeInTheDocument();

    await userEvent.click(within(row).getByRole('switch', { name: 'The link Course buyers: On' }));
    expect(await within(row).findByRole('alert')).toHaveTextContent('Failed to save the invite link.');
    expect(within(row).getByRole('switch', { name: 'The link Course buyers: On' })).toHaveAttribute('aria-checked', 'false');
  });

  it('creates a link with the form, checks its fields first, and shows the address after the create', async () => {
    const created = link('l9', 'Course buyers', { access_groups: [{ cuid: 'g1', name: 'Cohort', fed_by_rule: false }], max_uses: 100 });
    const create = vi.spyOn(knowiiApiClient.communities.inviteLinks, 'create').mockResolvedValue({ ok: true, message: '', data: created });
    renderManager();
    await rowOf('Course buyers');

    await userEvent.click(screen.getByRole('button', { name: 'New invite link' }));
    const dialog = await screen.findByRole('dialog', { name: 'New invite link' });
    expect(within(dialog).getByText('Only the managers see the name.')).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Access groups and tags' })).toHaveAccessibleDescription(INVITE_LINK_WORDS.warning);

    await userEvent.click(within(dialog).getByRole('button', { name: 'Create the link' }));
    expect(await within(dialog).findByText('Use between 2 and 64 characters')).toBeInTheDocument();
    expect(create).not.toHaveBeenCalled();

    await userEvent.type(within(dialog).getByLabelText('Name'), '  Course   buyers ');
    await userEvent.type(within(dialog).getByLabelText('Maximum number of joins'), '0');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Create the link' }));
    expect(await within(dialog).findByText('Enter a number from 1 to 10000')).toBeInTheDocument();

    await userEvent.clear(within(dialog).getByLabelText('Maximum number of joins'));
    await userEvent.type(within(dialog).getByLabelText('Maximum number of joins'), '100');
    await userEvent.type(within(dialog).getByLabelText('Date'), '2026-12-01');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Create the link' }));
    expect(await within(dialog).findByText('Enter a date and a time, or neither')).toBeInTheDocument();

    await userEvent.type(within(dialog).getByLabelText('Time'), '10:00');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Create the link' }));

    await waitFor(() =>
      expect(create).toHaveBeenCalledWith('c1', {
        name: 'Course buyers',
        access_groups: [],
        member_tags: [],
        expires_at: '2026-12-01T10:00:00+01:00',
        max_uses: 100,
      }),
    );
    const done = await screen.findByRole('dialog', { name: 'Invite link created' });
    expect(within(done).getByLabelText('Address of the link')).toHaveValue(created.url);
    expect(within(done).getByRole('button', { name: 'Copy the link Course buyers' })).toBeInTheDocument();
  });

  it('shows the error of a field that the server refuses', async () => {
    vi.spyOn(knowiiApiClient.communities.inviteLinks, 'create').mockResolvedValue({
      ok: false,
      status: 400,
      message: 'Validation failed',
      errors: { access_groups: ['A link cannot give a group that a rule on an entitlement feeds'] },
    });
    renderManager();
    await rowOf('Course buyers');

    await userEvent.click(screen.getByRole('button', { name: 'New invite link' }));
    const dialog = await screen.findByRole('dialog', { name: 'New invite link' });
    await userEvent.type(within(dialog).getByLabelText('Name'), 'Course buyers');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Create the link' }));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('A link cannot give a group that a rule on an entitlement feeds');
  });

  it('offers no group that a rule feeds, and says why', async () => {
    renderManager();
    await rowOf('Course buyers');

    await userEvent.click(screen.getByRole('button', { name: 'New invite link' }));
    const dialog = await screen.findByRole('dialog', { name: 'New invite link' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Access groups and tags' }));
    const picker = await screen.findByRole('dialog', { name: 'Access groups and tags' });

    expect(within(picker).getByText(INVITE_LINK_WORDS.pickerDescription)).toBeInTheDocument();
    expect(within(picker).getByRole('checkbox', { name: 'Cohort' })).toBeEnabled();
    const paid = within(picker).getByRole('checkbox', { name: 'Paid' });
    expect(paid).toBeDisabled();
    expect(paid).toHaveAccessibleDescription(INVITE_LINK_WORDS.fedByRuleShort);
  });

  it('edits a link with its values, and does not send an end that did not change', async () => {
    const update = vi
      .spyOn(knowiiApiClient.communities.inviteLinks, 'update')
      .mockResolvedValue({ ok: true, message: '', data: { ...ended, name: 'Webinar replay' } });
    renderManager();

    const row = await rowOf('Webinar');
    await userEvent.click(within(row).getByRole('button', { name: 'Edit the link Webinar' }));
    const dialog = await screen.findByRole('dialog', { name: 'Edit the invite link "Webinar"' });
    expect(within(dialog).getByLabelText('Date')).toHaveValue('2026-11-20');
    expect(within(dialog).getByLabelText('Time')).toHaveValue('10:00');

    await userEvent.clear(within(dialog).getByLabelText('Name'));
    await userEvent.type(within(dialog).getByLabelText('Name'), 'Webinar replay');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 'l3', { name: 'Webinar replay', max_uses: null }));
    expect(await screen.findByText('Invite link "Webinar replay" saved')).toBeInTheDocument();
    expect(await rowOf('Webinar replay')).toBeInTheDocument();
  });

  it('sends no groups and no tags when an edit changes only the name, also when the groups never load', async () => {
    vi.spyOn(knowiiApiClient.communities.accessGroups, 'list').mockReturnValue(new Promise(() => undefined));
    const cohort = link('l5', 'Cohort link', { access_groups: [{ cuid: 'g1', name: 'Cohort', fed_by_rule: false }], member_tags: [tag] });
    vi.spyOn(knowiiApiClient.communities.inviteLinks, 'list').mockResolvedValue({ ok: true, message: '', data: [cohort] });
    const update = vi
      .spyOn(knowiiApiClient.communities.inviteLinks, 'update')
      .mockResolvedValue({ ok: true, message: '', data: { ...cohort, name: 'Cohort link 2' } });
    renderManager();

    await userEvent.click(within(await rowOf('Cohort link')).getByRole('button', { name: 'Edit the link Cohort link' }));
    const dialog = await screen.findByRole('dialog', { name: 'Edit the invite link "Cohort link"' });
    await userEvent.type(within(dialog).getByLabelText('Name'), ' 2');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(update).toHaveBeenCalledOnce());
    expect(update.mock.calls[0]?.[2]).not.toHaveProperty('access_groups');
    expect(update.mock.calls[0]?.[2]).not.toHaveProperty('member_tags');
    expect(update.mock.calls[0]?.[2]).toEqual({ name: 'Cohort link 2', max_uses: null });
  });

  it('knows from the link a group that a rule feeds when the list does not say it, and still saves a change of the name', async () => {
    // The feature of the rules is off: the list of the groups gives no entitlement keys
    vi.spyOn(knowiiApiClient.communities.accessGroups, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: [group('g1', 'Cohort'), group('g2', 'Paid')],
    });
    const update = vi
      .spyOn(knowiiApiClient.communities.inviteLinks, 'update')
      .mockResolvedValue({ ok: true, message: '', data: { ...buyers, name: 'Course buyers 2026' } });
    renderManager();

    await userEvent.click(within(await rowOf('Course buyers')).getByRole('button', { name: 'Edit the link Course buyers' }));
    const dialog = await screen.findByRole('dialog', { name: 'Edit the invite link "Course buyers"' });
    await userEvent.type(within(dialog).getByLabelText('Name'), ' 2026');
    await userEvent.click(within(dialog).getByRole('button', { name: /Access groups and tags/ }));
    const picker = await screen.findByRole('dialog', { name: 'Access groups and tags' });
    const paid = within(picker).getByRole('checkbox', { name: 'Paid' });
    expect(paid).toBeDisabled();
    expect(paid).toHaveAccessibleDescription(INVITE_LINK_WORDS.fedByRuleShort);
    await userEvent.click(within(picker).getByRole('button', { name: 'Done' }));

    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));
    await waitFor(() => expect(update).toHaveBeenCalledOnce());
    expect(update.mock.calls[0]?.[2]).toEqual({ name: 'Course buyers 2026', max_uses: 100 });
  });

  it('drops only the groups known as fed by a rule when the groups change, and keeps a group that the list does not know', async () => {
    vi.spyOn(knowiiApiClient.communities.accessGroups, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: [group('g2', 'Paid', ['example.paid']), group('g3', 'Team')],
    });
    const update = vi.spyOn(knowiiApiClient.communities.inviteLinks, 'update').mockResolvedValue({ ok: true, message: '', data: buyers });
    renderManager();

    await userEvent.click(within(await rowOf('Course buyers')).getByRole('button', { name: 'Edit the link Course buyers' }));
    const dialog = await screen.findByRole('dialog', { name: 'Edit the invite link "Course buyers"' });
    await userEvent.click(within(dialog).getByRole('button', { name: /Access groups and tags/ }));
    const picker = await screen.findByRole('dialog', { name: 'Access groups and tags' });
    await userEvent.click(within(picker).getByRole('checkbox', { name: 'Team' }));
    await userEvent.click(within(picker).getByRole('button', { name: 'Done' }));
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(update).toHaveBeenCalledOnce());
    // g1 is not in the list of the choices: it stays (the server checks it); g2 is fed by a rule: it goes
    expect([...(update.mock.calls[0]?.[2].access_groups ?? [])].sort()).toEqual(['g1', 'g3']);
    expect(update.mock.calls[0]?.[2]).not.toHaveProperty('member_tags');
  });

  it('says in the question of a delete how many people joined, in words for 0, 1 and more', () => {
    expect(INVITE_LINK_WORDS.deleteDetails(0)).toBe('It stops working at once. Nobody joined through it. This cannot be undone.');
    expect(INVITE_LINK_WORDS.deleteDetails(1)).toBe(
      'It stops working at once. 1 person joined through it: if they are still a member, they keep their groups and tags. The list of who joined goes. This cannot be undone.',
    );
    expect(INVITE_LINK_WORDS.deleteDetails(3)).toBe(
      'It stops working at once. 3 people joined through it: those who are still members keep their groups and tags. The list of who joined goes. This cannot be undone.',
    );
  });

  it('asks before a delete with the number of members, and gives the focus back to the button on Cancel', async () => {
    const remove = vi
      .spyOn(knowiiApiClient.communities.inviteLinks, 'remove')
      .mockResolvedValue({ ok: true, message: '', data: undefined });
    renderManager();

    const row = await rowOf('Course buyers');
    const button = within(row).getByRole('button', { name: 'Delete the link Course buyers' });
    await userEvent.click(button);
    const dialog = await screen.findByRole('dialog', { name: 'Delete the invite link "Course buyers"?' });
    expect(`${INVITE_LINK_WORDS.deleteTitle('Course buyers')} ${INVITE_LINK_WORDS.deleteDetails(3)}`).toBe(
      'Delete the invite link "Course buyers"? It stops working at once. 3 people joined through it: those who are still members keep their groups and tags. The list of who joined goes. This cannot be undone.',
    );
    expect(dialog).toHaveTextContent(INVITE_LINK_WORDS.deleteDetails(3));

    await userEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));
    await waitFor(() => expect(button).toHaveFocus());
    expect(remove).not.toHaveBeenCalled();

    await userEvent.click(button);
    const again = await screen.findByRole('dialog', { name: 'Delete the invite link "Course buyers"?' });
    await userEvent.click(within(again).getByRole('button', { name: 'Delete' }));
    await waitFor(() => expect(remove).toHaveBeenCalledWith('c1', 'l1'));
    // While the question closes, its title keeps the name of the link
    expect(screen.queryByText('Delete the invite link ""?')).toBeNull();
    await waitFor(() => expect(screen.queryByText('Course buyers', { selector: 'span' })).toBeNull());
    // The row and its button went: the focus goes to "New invite link"
    await waitFor(() => expect(screen.getByRole('button', { name: 'New invite link' })).toHaveFocus());
  });

  it('shows who joined, with an empty state, and loads more', async () => {
    const uses = vi
      .spyOn(knowiiApiClient.communities.inviteLinks, 'uses')
      .mockResolvedValueOnce({
        ok: true,
        message: '',
        data: {
          items: [use('u1', 'Ada Example')],
          pagination: { limit: 20, offset: 0, previousOffset: null, nextOffset: 20, currentPage: 1, pageCount: 2, totalCount: 21 },
        },
      })
      .mockResolvedValueOnce({
        ok: true,
        message: '',
        data: {
          items: [use('u2', 'Grace Example')],
          pagination: { limit: 20, offset: 20, previousOffset: 0, nextOffset: null, currentPage: 2, pageCount: 2, totalCount: 21 },
        },
      })
      .mockResolvedValueOnce({
        ok: true,
        message: '',
        data: {
          items: [],
          pagination: { limit: 20, offset: 0, previousOffset: null, nextOffset: null, currentPage: 1, pageCount: 0, totalCount: 0 },
        },
      });
    renderManager();

    await userEvent.click(within(await rowOf('Course buyers')).getByRole('button', { name: 'Who joined through the link Course buyers' }));
    const dialog = await screen.findByRole('dialog', { name: 'Who joined through "Course buyers"' });
    expect(await within(dialog).findByText('Ada Example')).toBeInTheDocument();
    await userEvent.click(within(dialog).getByRole('button', { name: 'Load more' }));
    expect(await within(dialog).findByText('Grace Example')).toBeInTheDocument();
    expect(uses).toHaveBeenLastCalledWith('c1', 'l1', 20, 20);
    expect(within(dialog).queryByRole('button', { name: 'Load more' })).toBeNull();
    await userEvent.click(within(dialog).getByRole('button', { name: 'Done' }));

    await userEvent.click(within(await rowOf('Newsletter')).getByRole('button', { name: 'Who joined through the link Newsletter' }));
    const empty = await screen.findByRole('dialog', { name: 'Who joined through "Newsletter"' });
    expect(await within(empty).findByText('Nobody joined through this link, or they left.')).toBeInTheDocument();
  });

  it('has no automated violation (axe), with the list', async () => {
    const { container } = renderManager();
    await rowOf('Course buyers');

    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } });
    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
