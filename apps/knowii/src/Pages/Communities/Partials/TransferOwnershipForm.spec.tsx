import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community, CommunityMember, CommunityRole, Paginated } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import TransferOwnershipForm from './TransferOwnershipForm';

const member = (cuid: string, name: string, role: CommunityRole): CommunityMember => ({
  cuid,
  name,
  username: name.toLowerCase(),
  role,
  member_since: '2026-11-01T10:00:00.000000Z',
});

const page = (items: CommunityMember[]): Paginated<CommunityMember> => ({
  items,
  pagination: {
    limit: 100,
    offset: 0,
    previousOffset: null,
    nextOffset: null,
    currentPage: 1,
    pageCount: 1,
    totalCount: items.length,
  } as Paginated<CommunityMember>['pagination'],
});

const everyone = [
  member('o', 'Olivia', 'owner'),
  member('a', 'Alan', 'admin'),
  member('g', 'Grace', 'admin'),
  member('m', 'Margaret', 'moderator'),
  member('u', 'Ursula', 'member'),
];

const onTransferred = vi.fn();

function renderForm() {
  render(
    <AppDataProvider>
      <TransferOwnershipForm communityCuid="c1" communityName="Tests" onTransferred={onTransferred} />
    </AppDataProvider>,
  );
}

async function openDialog() {
  await userEvent.click(screen.getByRole('button', { name: 'Transfer the ownership' }));
  return screen.findByRole('dialog', { name: 'Transfer the ownership of Tests?' });
}

describe('TransferOwnershipForm', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    onTransferred.mockClear();
    vi.spyOn(knowiiApiClient.communities.members, 'list').mockResolvedValue({ ok: true, message: '', data: page(everyone) });
  });

  it('offers only the admins and sends the chosen admin with the password (#1339)', async () => {
    const transfer = vi
      .spyOn(knowiiApiClient.communities, 'transferOwnership')
      .mockResolvedValue({ ok: true, message: '', data: { cuid: 'c1' } as Community });
    renderForm();
    const dialog = await openDialog();

    const select = await within(dialog).findByRole('button', { name: /New owner/ });
    await waitFor(() => expect(select).toHaveFocus());
    await userEvent.click(select);
    const options = await screen.findAllByRole('option');
    expect(options.map((option) => option.textContent)).toEqual(['Alan (@alan)', 'Grace (@grace)']);
    await userEvent.click(screen.getByRole('option', { name: 'Grace (@grace)' }));
    await userEvent.type(within(dialog).getByLabelText('Your password, to confirm'), 'secret');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Transfer' }));

    await waitFor(() => expect(transfer).toHaveBeenCalledWith('c1', 'g', 'secret'));
    await waitFor(() => expect(onTransferred).toHaveBeenCalledTimes(1));
  });

  it('asks for a new owner before it calls the API (#1339)', async () => {
    const transfer = vi.spyOn(knowiiApiClient.communities, 'transferOwnership');
    renderForm();
    const dialog = await openDialog();

    await userEvent.type(await within(dialog).findByLabelText('Your password, to confirm'), 'secret');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Transfer' }));

    expect(await within(dialog).findByText('Choose the new owner.')).toBeInTheDocument();
    expect(transfer).not.toHaveBeenCalled();
  });

  it('shows the password error of the server and keeps the dialog open (#1339)', async () => {
    vi.spyOn(knowiiApiClient.communities, 'transferOwnership').mockResolvedValue({
      ok: false,
      status: 400,
      message: 'Invalid',
      errors: { password: ['The provided password does not match your current password.'] },
    });
    renderForm();
    const dialog = await openDialog();

    await userEvent.click(await within(dialog).findByRole('button', { name: /New owner/ }));
    await userEvent.click(await screen.findByRole('option', { name: 'Alan (@alan)' }));
    const password = within(dialog).getByLabelText('Your password, to confirm');
    await userEvent.type(password, 'wrong');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Transfer' }));

    expect(await within(dialog).findByText('The provided password does not match your current password.')).toBeInTheDocument();
    await waitFor(() => expect(password).toHaveFocus());
    expect(password).toHaveAttribute('aria-invalid', 'true');
    expect(onTransferred).not.toHaveBeenCalled();
  });

  it('explains what to do when the community has no admin (#1339)', async () => {
    vi.spyOn(knowiiApiClient.communities.members, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: page([member('o', 'Olivia', 'owner'), member('u', 'Ursula', 'member')]),
    });
    renderForm();
    const dialog = await openDialog();

    expect(await within(dialog).findByText(/This community has no admin/)).toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: 'Transfer' })).not.toBeInTheDocument();
    expect(within(dialog).queryByLabelText('Your password, to confirm')).not.toBeInTheDocument();
  });
});
