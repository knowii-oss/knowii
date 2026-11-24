import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import DeleteCommunityForm from './DeleteCommunityForm';

const { visit } = vi.hoisted(() => ({ visit: vi.fn() }));
vi.mock('@inertiajs/react', () => ({ router: { visit } }));

function renderForm() {
  render(
    <AppDataProvider>
      <DeleteCommunityForm communityCuid="c1" communityName="Knowledge Builders" />
    </AppDataProvider>,
  );
}

async function openDialog() {
  await userEvent.click(screen.getByRole('button', { name: 'Delete Community' }));
  return screen.findByRole('dialog', { name: 'Are you sure you want to delete this community?' });
}

describe('DeleteCommunityForm', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    visit.mockClear();
    vi.stubGlobal(
      'route',
      vi.fn((name: string) => `/${name}`),
    );
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('keeps the button off until the name of the community is typed', async () => {
    const remove = vi.spyOn(knowiiApiClient.communities, 'delete').mockResolvedValue(true);
    renderForm();
    const dialog = await openDialog();
    const confirm = within(dialog).getByRole('button', { name: 'Go ahead!' });

    expect(confirm).toBeDisabled();
    await userEvent.type(within(dialog).getByRole('textbox'), 'Knowledge');
    expect(confirm).toBeDisabled();
    expect(remove).not.toHaveBeenCalled();
  });

  it('sends the name that the member typed with the request, so that the server checks it again', async () => {
    const remove = vi.spyOn(knowiiApiClient.communities, 'delete').mockResolvedValue(true);
    renderForm();
    const dialog = await openDialog();

    await userEvent.type(within(dialog).getByRole('textbox'), 'knowledge builders');
    const confirm = within(dialog).getByRole('button', { name: 'Go ahead!' });
    await waitFor(() => expect(confirm).toBeEnabled());
    await userEvent.click(confirm);

    await waitFor(() => expect(remove).toHaveBeenCalledWith({ community: { cuid: 'c1' }, name: 'knowledge builders' }));
    await waitFor(() => expect(visit).toHaveBeenCalled());
  });

  it('stays on the page when the server refuses the deletion', async () => {
    vi.spyOn(knowiiApiClient.communities, 'delete').mockResolvedValue(false);
    renderForm();
    const dialog = await openDialog();

    await userEvent.type(within(dialog).getByRole('textbox'), 'Knowledge Builders');
    const confirm = within(dialog).getByRole('button', { name: 'Go ahead!' });
    await waitFor(() => expect(confirm).toBeEnabled());
    await userEvent.click(confirm);

    await waitFor(() => expect(knowiiApiClient.communities.delete).toHaveBeenCalled());
    expect(visit).not.toHaveBeenCalled();
  });
});
