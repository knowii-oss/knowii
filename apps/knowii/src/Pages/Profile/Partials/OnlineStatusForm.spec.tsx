import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import OnlineStatusForm from './OnlineStatusForm';

vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn() } }));

afterEach(() => {
  vi.restoreAllMocks();
});

describe('OnlineStatusForm (#1187, #1569)', () => {
  it('hides the online status at once with a switch and says "Saved", then puts the switch back and says the error when the server refuses', async () => {
    const update = vi
      .spyOn(knowiiApiClient.users, 'updateOnlineStatus')
      .mockResolvedValueOnce({ ok: true, message: '', data: undefined })
      .mockResolvedValueOnce({ ok: false, status: 500, message: 'Failed to change your online status.', errors: {} });
    render(
      <AppDataProvider>
        <OnlineStatusForm showOnlineStatus />
      </AppDataProvider>,
    );

    const toggle = screen.getByRole('switch', { name: 'Show the other members when I am online' });
    expect(toggle).toBeChecked();
    expect(toggle).toHaveAccessibleDescription(/When this is off, you never appear online/);
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();

    await userEvent.click(toggle);
    expect(update).toHaveBeenCalledWith(false);
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved'));
    expect(toggle).not.toBeChecked();
    expect(toggle).toHaveAttribute('aria-busy', 'false');

    await userEvent.click(toggle);
    expect(update).toHaveBeenLastCalledWith(true);
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Failed to change your online status.'));
    expect(toggle).not.toBeChecked();
    expect(toggle).toHaveFocus();
  });
});
