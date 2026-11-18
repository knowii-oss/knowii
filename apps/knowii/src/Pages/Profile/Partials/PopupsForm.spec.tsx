import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { knowiiApiClient } from '@knowii/common';
import { PopupsForm } from './PopupsForm';

type PopupsResult = Awaited<ReturnType<typeof knowiiApiClient.users.setNotificationPopups>>;

const shared = vi.hoisted(() => ({ reload: vi.fn() }));

vi.mock('@inertiajs/react', () => ({ router: { reload: shared.reload } }));

afterEach(() => {
  vi.restoreAllMocks();
  shared.reload.mockClear();
});

const popupsSwitch = () => screen.getByRole('switch', { name: 'Show pop-ups' });

describe('PopupsForm (#1476, #1569)', () => {
  it('is a real switch, not a checkbox with the role of a switch', () => {
    render(<PopupsForm popups onStatus={vi.fn()} />);

    const toggle = popupsSwitch();
    expect(toggle.tagName).toBe('BUTTON');
    expect(toggle).toBeChecked();
    expect(toggle).toHaveAccessibleDescription('A short message when something new needs you. Nothing else pops up.');
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
    expect(document.querySelector('input[type="checkbox"]')).toBeNull();
  });

  it('saves at once, is busy while it saves and keeps the focus, then says "Saved" in the status of the page', async () => {
    let answer: (result: PopupsResult) => void = () => undefined;
    const save = vi
      .spyOn(knowiiApiClient.users, 'setNotificationPopups')
      .mockReturnValue(new Promise<PopupsResult>((resolve) => (answer = resolve)));
    const onStatus = vi.fn();
    render(<PopupsForm popups onStatus={onStatus} />);

    popupsSwitch().focus();
    await userEvent.keyboard(' ');

    expect(save).toHaveBeenCalledWith(false);
    expect(popupsSwitch()).not.toBeChecked();
    expect(popupsSwitch()).toHaveAttribute('aria-busy', 'true');
    await userEvent.keyboard(' ');
    expect(save).toHaveBeenCalledOnce();
    expect(popupsSwitch()).toHaveFocus();

    answer({ ok: true, message: '', data: { time_zone: null, notification_popups: false } });

    await waitFor(() => expect(onStatus).toHaveBeenCalledWith('Saved'));
    expect(shared.reload).toHaveBeenCalledWith({ only: ['auth'] });
    expect(popupsSwitch()).not.toBeChecked();
    expect(popupsSwitch()).toHaveAttribute('aria-busy', 'false');
  });

  it('puts the switch back and says "Not saved. Retry." when the server refuses, and the switch keeps the focus', async () => {
    vi.spyOn(knowiiApiClient.users, 'setNotificationPopups').mockResolvedValue({ ok: false, status: 500, message: 'Failed', errors: {} });
    const onStatus = vi.fn();
    render(<PopupsForm popups={false} onStatus={onStatus} />);

    await userEvent.click(popupsSwitch());

    expect(await screen.findByRole('alert')).toHaveTextContent('Not saved. Retry.');
    expect(popupsSwitch()).not.toBeChecked();
    expect(popupsSwitch()).toHaveFocus();
    expect(onStatus).not.toHaveBeenCalled();
    expect(shared.reload).not.toHaveBeenCalled();
  });
});
