import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { MemberTag } from '@knowii/common';
import { AppDataProvider, knowiiApiClient, MEMBER_TAG_PERSONAL_DATA_NOTICE } from '@knowii/common';
import { deleteQuestion, MemberTagsManager, showToMembersQuestion } from './MemberTagsManager';

const tag = (cuid: string, name: string, changes: Partial<MemberTag> = {}): MemberTag => ({
  cuid,
  name,
  color: 'gray',
  visibility: 'managers',
  members_count: 0,
  created_at: '2026-11-22T10:00:00.000000Z',
  ...changes,
});

const founder = tag('t1', 'Founder', { color: 'purple', visibility: 'members', members_count: 2 });
const onboarded = tag('t2', 'Onboarded', { members_count: 3 });

function renderManager(canManageMemberTags = true, onChanged = vi.fn()) {
  const result = render(
    <AppDataProvider>
      <MemberTagsManager communityCuid="c1" permissions={{ canManageMemberTags }} onChanged={onChanged} />
    </AppDataProvider>,
  );
  return { ...result, onChanged };
}

beforeEach(() => {
  vi.restoreAllMocks();
  vi.spyOn(knowiiApiClient.communities.memberTags, 'list').mockResolvedValue({ ok: true, message: '', data: [founder, onboarded] });
});

describe('the words of the questions (#1389)', () => {
  it('says the number of members', () => {
    expect(showToMembersQuestion(3)).toBe('3 members have this tag. Every member of the community will see it on them.');
    expect(showToMembersQuestion(1)).toBe('1 member has this tag. Every member of the community will see it on them.');
    expect(deleteQuestion(3)).toBe('3 members lose it. This cannot be undone.');
    expect(deleteQuestion(1)).toBe('1 member loses it. This cannot be undone.');
  });
});

describe('MemberTagsManager (#1389)', () => {
  it('lists the tags with their color, who sees them in words and their member count', async () => {
    renderManager();

    const list = await screen.findByRole('list', { name: 'Member tags' });
    const rows = within(list)
      .getAllByRole('listitem')
      .filter((item) => item.parentElement === list);
    expect(rows).toHaveLength(2);
    expect(rows[0]).toHaveTextContent('Founder');
    expect(rows[0]).toHaveTextContent('Members, 2 members');
    expect(rows[1]).toHaveTextContent('Onboarded (managers only)');
    expect(rows[1]).toHaveTextContent('Managers only, 3 members');
    expect(within(rows[0] as HTMLElement).getByText('Founder').parentElement).toHaveClass('border-tag-purple');
  });

  it('is read only without the right to define the tags: no form, no edit, no delete', async () => {
    renderManager(false);

    await screen.findByRole('list', { name: 'Member tags' });
    expect(screen.queryByRole('form', { name: 'Create a tag' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Edit/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Delete/ })).not.toBeInTheDocument();
  });

  it('says so when the community has no tag', async () => {
    vi.spyOn(knowiiApiClient.communities.memberTags, 'list').mockResolvedValue({ ok: true, message: '', data: [] });
    renderManager();

    expect(await screen.findByText('No member tags yet')).toBeInTheDocument();
    expect(screen.queryByRole('list', { name: 'Member tags' })).not.toBeInTheDocument();
  });

  it('creates a tag with the form, its text on personal data tied to it, and gives the focus back to the name', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.memberTags, 'create')
      .mockResolvedValue({ ok: true, message: '', data: tag('t3', 'Early bird', { color: 'teal', visibility: 'members' }) });
    const { onChanged } = renderManager();

    const form = await screen.findByRole('form', { name: 'Create a tag' });
    expect(form).toHaveAccessibleDescription(MEMBER_TAG_PERSONAL_DATA_NOTICE);
    const name = within(form).getByRole('textbox', { name: 'Name' });
    await userEvent.type(name, '  Early   bird ');
    await userEvent.click(within(form).getByRole('button', { name: /Color/ }));
    await userEvent.click(await screen.findByRole('option', { name: 'Teal' }));
    // The tag as it will show
    expect(within(form).getByText('Shows as').parentElement?.querySelector('.border-tag-teal')).not.toBeNull();
    await userEvent.click(within(form).getByRole('radio', { name: 'Members' }));
    await userEvent.click(within(form).getByRole('button', { name: 'Create the tag' }));

    await waitFor(() => expect(create).toHaveBeenCalledWith('c1', { name: 'Early bird', color: 'teal', visibility: 'members' }));
    await waitFor(() => expect(name).toHaveValue(''));
    expect(name).toHaveFocus();
    expect(onChanged).toHaveBeenCalled();
  });

  it('starts a new tag as gray and for the managers only', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.memberTags, 'create')
      .mockResolvedValue({ ok: true, message: '', data: tag('t3', 'Staff') });
    renderManager();

    const form = await screen.findByRole('form', { name: 'Create a tag' });
    expect(within(form).getByRole('radio', { name: 'Managers only' })).toBeChecked();
    await userEvent.type(within(form).getByRole('textbox', { name: 'Name' }), 'Staff');
    await userEvent.click(within(form).getByRole('button', { name: 'Create the tag' }));

    await waitFor(() => expect(create).toHaveBeenCalledWith('c1', { name: 'Staff', color: 'gray', visibility: 'managers' }));
  });

  it('shows the errors of the name: in the form, then from the server', async () => {
    const create = vi.spyOn(knowiiApiClient.communities.memberTags, 'create').mockResolvedValue({
      ok: false,
      status: 400,
      message: 'The given data was invalid.',
      errors: { name: ['A tag with this name exists already.'] },
    });
    renderManager();

    const form = await screen.findByRole('form', { name: 'Create a tag' });
    const name = within(form).getByRole('textbox', { name: 'Name' });
    await userEvent.type(name, 'x');
    await userEvent.click(within(form).getByRole('button', { name: 'Create the tag' }));
    expect(await within(form).findByRole('alert')).toHaveTextContent('Use between 2 and 32 characters');
    expect(create).not.toHaveBeenCalled();

    await userEvent.clear(name);
    await userEvent.type(name, 'founder');
    await userEvent.click(within(form).getByRole('button', { name: 'Create the tag' }));
    expect(await within(form).findByText('A tag with this name exists already.')).toBeInTheDocument();
    expect(name).toHaveAccessibleDescription('A tag with this name exists already.');
  });

  it('asks before a tag that members have is shown to every member, with their number', async () => {
    const update = vi
      .spyOn(knowiiApiClient.communities.memberTags, 'update')
      .mockResolvedValue({ ok: true, message: '', data: { ...onboarded, visibility: 'members' } });
    renderManager();

    await userEvent.click(await screen.findByRole('button', { name: 'Edit Onboarded' }));
    const dialog = await screen.findByRole('dialog', { name: 'Edit the tag "Onboarded"' });
    await userEvent.click(within(dialog).getByRole('radio', { name: 'Members' }));
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    expect(
      await within(dialog).findByText('3 members have this tag. Every member of the community will see it on them.'),
    ).toBeInTheDocument();
    expect(update).not.toHaveBeenCalled();
    await userEvent.click(within(dialog).getByRole('button', { name: 'Show it to the members' }));

    await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 't2', { name: 'Onboarded', color: 'gray', visibility: 'members' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  it('renames a tag without a question when no member sees more', async () => {
    const update = vi
      .spyOn(knowiiApiClient.communities.memberTags, 'update')
      .mockResolvedValue({ ok: true, message: '', data: { ...founder, name: 'Founders' } });
    renderManager();

    await userEvent.click(await screen.findByRole('button', { name: 'Edit Founder' }));
    const dialog = await screen.findByRole('dialog', { name: 'Edit the tag "Founder"' });
    expect(within(dialog).getByText(MEMBER_TAG_PERSONAL_DATA_NOTICE)).toBeInTheDocument();
    const name = within(dialog).getByRole('textbox', { name: 'Name' });
    await userEvent.clear(name);
    await userEvent.type(name, 'Founders');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 't1', { name: 'Founders', color: 'purple', visibility: 'members' }));
  });

  it('asks before a delete, with the number of members who lose the tag', async () => {
    const remove = vi.spyOn(knowiiApiClient.communities.memberTags, 'remove').mockResolvedValue({ ok: true, message: '', data: undefined });
    const { onChanged } = renderManager();

    await userEvent.click(await screen.findByRole('button', { name: 'Delete Founder' }));
    const dialog = await screen.findByRole('dialog', { name: 'Delete the tag "Founder"?' });
    expect(dialog).toHaveTextContent('2 members lose it. This cannot be undone.');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

    await waitFor(() => expect(remove).toHaveBeenCalledWith('c1', 't1'));
    await waitFor(() => expect(screen.queryByText('Members, 2 members')).not.toBeInTheDocument());
    expect(onChanged).toHaveBeenCalled();
  });

  it('has no automated violation (axe), with the form and the list', async () => {
    const { container } = renderManager();
    await screen.findByRole('list', { name: 'Member tags' });

    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } });
    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
