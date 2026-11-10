import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import OnlineStatusForm from './OnlineStatusForm';

vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn() } }));

describe('OnlineStatusForm (#1187)', () => {
  it('hides the online status at once, and puts the box back when the server refuses', async () => {
    const update = vi
      .spyOn(knowiiApiClient.users, 'updateOnlineStatus')
      .mockResolvedValueOnce({ ok: true, message: '', data: undefined })
      .mockResolvedValueOnce({ ok: false, status: 500, message: 'Failed to change your online status.', errors: {} });
    render(
      <AppDataProvider>
        <OnlineStatusForm showOnlineStatus />
      </AppDataProvider>,
    );

    const box = screen.getByRole('checkbox', { name: /Show the other members when I am online/ });
    expect(box).toBeChecked();

    await userEvent.click(box);
    expect(update).toHaveBeenCalledWith(false);
    await waitFor(() => expect(box).not.toBeChecked());
    await waitFor(() => expect(box).toBeEnabled());

    await userEvent.click(box);
    expect(update).toHaveBeenLastCalledWith(true);
    await waitFor(() => expect(box).not.toBeChecked());
  });
});
