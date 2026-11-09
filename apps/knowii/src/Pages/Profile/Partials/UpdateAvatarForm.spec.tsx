import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import UpdateAvatarForm from './UpdateAvatarForm';

const reload = vi.fn();
vi.mock('@inertiajs/react', () => ({ router: { reload: (...args: unknown[]) => reload(...args) } }));

function renderForm(avatarUrl: string | null) {
  render(
    <AppDataProvider>
      <UpdateAvatarForm name="Ada Lovelace" avatarUrl={avatarUrl} />
    </AppDataProvider>,
  );
}

describe('UpdateAvatarForm', () => {
  it('shows the initials and uploads the chosen picture (#109)', async () => {
    const update = vi.spyOn(knowiiApiClient.users, 'updateAvatar').mockResolvedValue({ ok: true, message: '', data: '/storage/a.webp' });
    renderForm(null);

    expect(screen.getByText('AL')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Remove' })).not.toBeInTheDocument();

    const file = new File(['x'], 'me.png', { type: 'image/png' });
    await userEvent.upload(document.getElementById('avatar-file') as HTMLInputElement, file);

    await waitFor(() => expect(update).toHaveBeenCalledWith(file));
    await waitFor(() => expect(reload).toHaveBeenCalledWith({ only: ['auth'] }));
  });

  it('shows the error of the server (#109)', async () => {
    vi.spyOn(knowiiApiClient.users, 'updateAvatar').mockResolvedValue({
      ok: false,
      status: 400,
      message: 'Invalid',
      errors: { file: ['This file is not a supported image (JPEG, PNG, WebP or GIF)'] },
    });
    renderForm(null);

    await userEvent.upload(document.getElementById('avatar-file') as HTMLInputElement, new File(['x'], 'a.png', { type: 'image/png' }));

    expect(await screen.findByText('This file is not a supported image (JPEG, PNG, WebP or GIF)')).toBeInTheDocument();
  });

  it('removes the picture (#109)', async () => {
    const remove = vi.spyOn(knowiiApiClient.users, 'deleteAvatar').mockResolvedValue({ ok: true, message: '', data: undefined });
    renderForm('/storage/a.webp');

    expect(screen.getByRole('button', { name: 'Change the picture' })).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Remove' }));

    await waitFor(() => expect(remove).toHaveBeenCalled());
  });
});
