import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { JoinPolicySetting } from './JoinPolicySetting';

const community = {
  cuid: 'c1',
  name: 'Tests',
  slug: 'tests',
  description: '',
  visibility: 'private',
  join_policy: 'invitation',
  created_at: new Date(),
  updated_at: new Date(),
} as Community;

function renderSetting(value: Community = community, onSaved = vi.fn()) {
  render(
    <AppDataProvider>
      <JoinPolicySetting community={value} onSaved={onSaved} />
    </AppDataProvider>,
  );
  return onSaved;
}

describe('JoinPolicySetting (#1486)', () => {
  it('shows the two choices, and says what "open" means', () => {
    renderSetting();

    expect(screen.getByRole('radio', { name: /By invitation/ })).toBeChecked();
    expect(screen.getByRole('radio', { name: /Open/ })).not.toBeChecked();
    expect(screen.getByRole('radio', { name: /Open/ })).toHaveAccessibleDescription(
      'Anyone with an account can join this community and read its members-only spaces.',
    );
  });

  it('shows "Open" for an open community', () => {
    renderSetting({ ...community, join_policy: 'open' });

    expect(screen.getByRole('radio', { name: /Open/ })).toBeChecked();
  });

  it('saves a change at once, with the keyboard', async () => {
    const saved = { ...community, join_policy: 'open' } as Community;
    const update = vi.spyOn(knowiiApiClient.communities, 'updateJoinPolicy').mockResolvedValue({ ok: true, message: '', data: saved });
    const onSaved = renderSetting();

    screen.getByRole('radio', { name: /By invitation/ }).focus();
    await userEvent.keyboard('{ArrowDown}');

    await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 'open'));
    await waitFor(() => expect(onSaved).toHaveBeenCalledWith(saved));
  });

  it('goes back to the saved value when the save fails', async () => {
    vi.spyOn(knowiiApiClient.communities, 'updateJoinPolicy').mockResolvedValue({ ok: false, status: 403, message: 'No', errors: {} });
    const onSaved = renderSetting();

    await userEvent.click(screen.getByRole('radio', { name: /Open/ }));

    await waitFor(() => expect(screen.getByRole('radio', { name: /By invitation/ })).toBeChecked());
    expect(onSaved).not.toHaveBeenCalled();
  });
});
