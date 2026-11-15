import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Space, SpaceGroup } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { SpaceDialog } from './SpaceDialog';

const spaceGroups = [{ cuid: 'sg1', name: 'Start Here', position: 1 }] as SpaceGroup[];

const space = (changes: Partial<Space> = {}): Space =>
  ({
    cuid: 's1',
    name: 'Open discussion',
    slug: 'open-discussion',
    description: '',
    emoji: '',
    type: 'discussion',
    visibility: 'members',
    posting_policy: 'members',
    space_group_cuid: 'sg1',
    position: 1,
    url: null,
    access_groups: [],
    default_notification_level: null,
    ...changes,
  }) as Space;

function renderDialog(current?: Space) {
  render(
    <AppDataProvider>
      <SpaceDialog
        open
        communityCuid="c1"
        spaceGroups={spaceGroups}
        space={current}
        defaultSpaceGroupCuid="sg1"
        onClose={vi.fn()}
        onSaved={vi.fn()}
      />
    </AppDataProvider>,
  );
}

describe('the default level of a space in the space form (#1497)', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('shows "What members get by default" with a sentence that says the choice of a member always wins', async () => {
    renderDialog(space());
    const dialog = await screen.findByRole('dialog', { name: 'Edit Open discussion' });

    expect(within(dialog).getByRole('button', { name: /What members get by default/ })).toHaveTextContent('Only what is for me');
    expect(within(dialog).getByText(/The choice of a member always wins/)).toBeInTheDocument();
  });

  it('offers Everything for a discussion space, and only "Only what is for me" for another type', async () => {
    renderDialog(space());
    const dialog = await screen.findByRole('dialog', { name: 'Edit Open discussion' });
    await userEvent.click(within(dialog).getByRole('button', { name: /What members get by default/ }));
    expect(
      within(await screen.findByRole('listbox'))
        .getAllByRole('option')
        .map((option) => option.textContent),
    ).toEqual(['Everything', 'Only what is for me']);
  });

  it('offers no Everything for a chat space', async () => {
    renderDialog(space({ type: 'chat', name: 'Lounge' }));
    const dialog = await screen.findByRole('dialog', { name: 'Edit Lounge' });
    await userEvent.click(within(dialog).getByRole('button', { name: /What members get by default/ }));

    expect(
      within(await screen.findByRole('listbox'))
        .getAllByRole('option')
        .map((option) => option.textContent),
    ).toEqual(['Only what is for me']);
    expect(within(dialog).getByText(/Everything is only for a discussion space/)).toBeInTheDocument();
  });

  it('shows the built-in default when the manager chose nothing: Everything where only moderators post', async () => {
    renderDialog(space({ posting_policy: 'moderators', name: 'News' }));
    const dialog = await screen.findByRole('dialog', { name: 'Edit News' });

    expect(within(dialog).getByRole('button', { name: /What members get by default/ })).toHaveTextContent('Everything');
  });

  it('sends the default level only when the manager changed it', async () => {
    const update = vi.spyOn(knowiiApiClient.communities.spaces, 'update').mockResolvedValue({ ok: true, message: '', data: space() });
    renderDialog(space());
    const dialog = await screen.findByRole('dialog', { name: 'Edit Open discussion' });

    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));
    await waitFor(() => expect(update).toHaveBeenCalledTimes(1));
    expect(update.mock.calls[0]?.[2]).not.toHaveProperty('default_notification_level');

    await userEvent.click(within(dialog).getByRole('button', { name: /What members get by default/ }));
    await userEvent.click(await screen.findByRole('option', { name: 'Everything' }));
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(update).toHaveBeenCalledTimes(2));
    expect(update.mock.calls[1]?.[2]).toMatchObject({ default_notification_level: 'all' });
  });

  it('is not in the form of a new space, nor of a link space', async () => {
    renderDialog();
    const dialog = await screen.findByRole('dialog', { name: 'New space' });
    expect(within(dialog).queryByText('What members get by default')).toBeNull();
  });

  it('is not in the form of a link space', async () => {
    renderDialog(space({ type: 'link', url: 'https://example.com', name: 'Shop' }));
    const dialog = await screen.findByRole('dialog', { name: 'Edit Shop' });

    expect(within(dialog).queryByText('What members get by default')).toBeNull();
  });
});
