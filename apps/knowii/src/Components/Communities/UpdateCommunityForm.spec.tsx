import { beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { UpdateCommunityForm } from './UpdateCommunityForm';

const community = {
  cuid: 'c1',
  name: 'Tests',
  slug: 'tests',
  description: 'Old description',
  visibility: 'private',
  moderator_permissions: { manage_spaces: false, manage_members: false, pin_posts: true, delete_content: true },
  created_at: new Date(),
  updated_at: new Date(),
} as Community;

function renderForm(value: Community = community, onSaved = vi.fn()) {
  render(
    <AppDataProvider>
      <UpdateCommunityForm community={value} onSaved={onSaved} />
    </AppDataProvider>,
  );
  return onSaved;
}

describe('UpdateCommunityForm', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    vi.spyOn(knowiiApiClient.communities, 'isNameAvailable').mockResolvedValue({ ok: true, message: '', data: { available: true } });
  });

  it('saves the changed details and keeps Save disabled until something changes (#1129)', async () => {
    const saved = { ...community, name: 'Renamed', visibility: 'public' as const };
    const update = vi.spyOn(knowiiApiClient.communities, 'update').mockResolvedValue({ ok: true, message: '', data: saved });
    const onSaved = renderForm();

    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
    await userEvent.clear(screen.getByLabelText('Name'));
    await userEvent.type(screen.getByLabelText('Name'), 'Renamed');
    await userEvent.click(screen.getByRole('radio', { name: /Public/ }));
    expect(await screen.findByText('This name is available.')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() =>
      expect(update).toHaveBeenCalledWith('c1', { name: 'Renamed', description: 'Old description', visibility: 'public' }),
    );
    await waitFor(() => expect(onSaved).toHaveBeenCalledWith(saved));
  });

  it('refuses a name that is too short before calling the API (#1129)', async () => {
    const update = vi.spyOn(knowiiApiClient.communities, 'update');
    renderForm();

    await userEvent.clear(screen.getByLabelText('Name'));
    await userEvent.type(screen.getByLabelText('Name'), 'ab');
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText(/Please choose a name/)).toBeInTheDocument();
    expect(update).not.toHaveBeenCalled();
  });

  it('shows the field errors of the server (#1129)', async () => {
    vi.spyOn(knowiiApiClient.communities, 'update').mockResolvedValue({
      ok: false,
      status: 400,
      message: 'Invalid',
      errors: { name: ['The name is not available'] },
    });
    renderForm();

    await userEvent.type(screen.getByLabelText('Name'), ' two');
    await screen.findByText('This name is available.');
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('The name is not available')).toBeInTheDocument();
  });

  it('offers no visibility change for a personal community (#1129)', () => {
    renderForm({ ...community, visibility: 'personal' });

    expect(screen.queryByRole('radio')).not.toBeInTheDocument();
    expect(screen.getByText('Personal. A personal community stays personal.')).toBeInTheDocument();
  });

  it('checks a new name with the cuid of the community and blocks Save while the name is taken (#1136)', async () => {
    const check = vi
      .spyOn(knowiiApiClient.communities, 'isNameAvailable')
      .mockResolvedValue({ ok: true, message: '', data: { available: false } });
    renderForm();

    await userEvent.type(screen.getByLabelText('Name'), ' two');

    expect(await screen.findByText('This name is already taken. Choose another one.')).toBeInTheDocument();
    expect(check).toHaveBeenCalledTimes(1);
    expect(check).toHaveBeenCalledWith({ name: 'Tests two', community: 'c1' }, expect.any(AbortSignal));
    expect(screen.getByLabelText('Name')).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
  });

  it('does not check the current name or the name of a personal community (#1136)', async () => {
    const check = vi.spyOn(knowiiApiClient.communities, 'isNameAvailable');
    renderForm();
    await userEvent.type(screen.getByLabelText('Description'), ' and more');
    expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled();

    cleanup();
    renderForm({ ...community, cuid: 'p1', visibility: 'personal' });
    await userEvent.type(screen.getByLabelText('Name'), ' two');
    await new Promise((resolve) => setTimeout(resolve, 400));

    expect(check).not.toHaveBeenCalled();
  });
});
