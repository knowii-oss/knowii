import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ApiResult, Community } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { DirectMessagesSetting } from './DirectMessagesSetting';

const community = {
  cuid: 'c1',
  name: 'Tests',
  slug: 'tests',
  description: '',
  visibility: 'private',
  direct_messages_enabled: true,
  created_at: new Date(),
  updated_at: new Date(),
} as Community;

function renderSetting(value: Community = community, onSaved = vi.fn()) {
  render(
    <AppDataProvider>
      <DirectMessagesSetting community={value} onSaved={onSaved} />
    </AppDataProvider>,
  );
  return onSaved;
}

const directMessagesSwitch = () => screen.getByRole('switch', { name: 'Members can write private messages to each other' });

afterEach(() => {
  vi.restoreAllMocks();
});

describe('DirectMessagesSetting (#1186, #1569)', () => {
  it('is a switch, on unless the community turned it off, that says what it does', () => {
    renderSetting({ ...community, direct_messages_enabled: undefined });

    const toggle = directMessagesSwitch();
    expect(toggle).toBeChecked();
    expect(toggle).toHaveAccessibleDescription(/One-to-one or in groups of up to 10\./);
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
  });

  it('shows the saved value', () => {
    renderSetting({ ...community, direct_messages_enabled: false });

    expect(directMessagesSwitch()).not.toBeChecked();
  });

  it('saves a change at once, without a question: "Saving", then "Saved", and the switch keeps the focus', async () => {
    const saved = { ...community, direct_messages_enabled: false } as Community;
    let answer: (result: ApiResult<Community>) => void = () => undefined;
    const update = vi
      .spyOn(knowiiApiClient.communities, 'update')
      .mockReturnValue(new Promise<ApiResult<Community>>((resolve) => (answer = resolve)));
    const onSaved = renderSetting();

    directMessagesSwitch().focus();
    await userEvent.keyboard(' ');

    expect(update).toHaveBeenCalledWith('c1', { direct_messages_enabled: false });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(directMessagesSwitch()).not.toBeChecked();
    expect(screen.getByRole('status')).toHaveTextContent('Saving');

    // A second press while it saves does nothing
    await userEvent.keyboard(' ');
    expect(update).toHaveBeenCalledOnce();
    expect(directMessagesSwitch()).toHaveFocus();

    answer({ ok: true, message: '', data: saved });

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved'));
    expect(onSaved).toHaveBeenCalledWith(saved);
    expect(directMessagesSwitch()).not.toBeChecked();
    expect(directMessagesSwitch()).toHaveFocus();
  });

  it('goes back to the saved value and says the error when the save fails', async () => {
    vi.spyOn(knowiiApiClient.communities, 'update').mockResolvedValue({
      ok: false,
      status: 500,
      message: 'Failed to save the community.',
      errors: {},
    });
    const onSaved = renderSetting();

    await userEvent.click(directMessagesSwitch());

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Failed to save the community.'));
    expect(directMessagesSwitch()).toBeChecked();
    expect(directMessagesSwitch()).toHaveFocus();
    expect(onSaved).not.toHaveBeenCalled();
  });
});
