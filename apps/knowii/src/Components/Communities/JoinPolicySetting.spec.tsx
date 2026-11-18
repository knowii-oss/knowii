import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ApiResult, Community } from '@knowii/common';
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

const QUESTION = 'Open this community to anyone with an account?';

function renderSetting(value: Community = community, onSaved = vi.fn()) {
  render(
    <AppDataProvider>
      <JoinPolicySetting community={value} onSaved={onSaved} />
    </AppDataProvider>,
  );
  return onSaved;
}

const byInvitation = () => screen.getByRole('radio', { name: /By invitation/ });
const open = () => screen.getByRole('radio', { name: /Open/ });

afterEach(() => {
  vi.restoreAllMocks();
});

describe('JoinPolicySetting (#1486, #1569)', () => {
  it('shows the two choices, says what "open" means, and has an empty status', () => {
    renderSetting();

    expect(byInvitation()).toBeChecked();
    expect(open()).not.toBeChecked();
    expect(open()).toHaveAccessibleDescription('Anyone with an account can join this community and read its members-only spaces.');
    expect(screen.getByRole('status')).toHaveTextContent('');
  });

  it('shows "Open" for an open community', () => {
    renderSetting({ ...community, join_policy: 'open' });

    expect(open()).toBeChecked();
  });

  it('asks before it opens the community, and saves nothing before the yes', async () => {
    const update = vi.spyOn(knowiiApiClient.communities, 'updateJoinPolicy');
    renderSetting();

    byInvitation().focus();
    await userEvent.keyboard('{ArrowDown}');

    const dialog = await screen.findByRole('dialog', { name: QUESTION });
    expect(dialog).toHaveAccessibleDescription(
      'Anyone with an account can then join without an invitation. If you change it back later, the people who joined stay members.',
    );
    expect(within(dialog).getByRole('button', { name: 'Open the community' })).toBeInTheDocument();
    await waitFor(() => expect(within(dialog).getByRole('button', { name: 'Cancel' })).toHaveFocus());
    expect(update).not.toHaveBeenCalled();
    expect(byInvitation()).toBeChecked();
  });

  it('"Cancel" keeps "By invitation" and gives the focus back to the choice', async () => {
    const update = vi.spyOn(knowiiApiClient.communities, 'updateJoinPolicy');
    const onSaved = renderSetting();

    await userEvent.click(open());
    await userEvent.click(within(await screen.findByRole('dialog', { name: QUESTION })).getByRole('button', { name: 'Cancel' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await waitFor(() => expect(open()).toHaveFocus());
    expect(byInvitation()).toBeChecked();
    expect(open()).not.toBeChecked();
    expect(update).not.toHaveBeenCalled();
    expect(onSaved).not.toHaveBeenCalled();
  });

  it('Escape keeps "By invitation" and gives the focus back to the choice', async () => {
    const update = vi.spyOn(knowiiApiClient.communities, 'updateJoinPolicy');
    renderSetting();

    byInvitation().focus();
    await userEvent.keyboard('{ArrowDown}');
    await screen.findByRole('dialog', { name: QUESTION });
    await userEvent.keyboard('{Escape}');

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await waitFor(() => expect(open()).toHaveFocus());
    expect(byInvitation()).toBeChecked();
    expect(update).not.toHaveBeenCalled();
  });

  it('"Open the community" saves, says "Saved", and gives the focus back to the choice', async () => {
    const saved = { ...community, join_policy: 'open' } as Community;
    const update = vi.spyOn(knowiiApiClient.communities, 'updateJoinPolicy').mockResolvedValue({ ok: true, message: '', data: saved });
    const onSaved = renderSetting();

    byInvitation().focus();
    await userEvent.keyboard('{ArrowDown}');
    await userEvent.click(
      within(await screen.findByRole('dialog', { name: QUESTION })).getByRole('button', { name: 'Open the community' }),
    );

    expect(update).toHaveBeenCalledWith('c1', 'open');
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved'));
    expect(onSaved).toHaveBeenCalledWith(saved);
    expect(open()).toBeChecked();
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await waitFor(() => expect(open()).toHaveFocus());
  });

  it('saves "By invitation" at once, without a question: "Saving", then "Saved"', async () => {
    const saved = { ...community, join_policy: 'invitation' } as Community;
    let answer: (result: ApiResult<Community>) => void = () => undefined;
    const update = vi
      .spyOn(knowiiApiClient.communities, 'updateJoinPolicy')
      .mockReturnValue(new Promise<ApiResult<Community>>((resolve) => (answer = resolve)));
    const onSaved = renderSetting({ ...community, join_policy: 'open' });

    open().focus();
    await userEvent.keyboard('{ArrowUp}');

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(update).toHaveBeenCalledWith('c1', 'invitation');
    expect(byInvitation()).toBeChecked();
    expect(screen.getByRole('group', { name: 'Who can join this community' })).toHaveAttribute('aria-busy', 'true');
    expect(screen.getByRole('status')).toHaveTextContent('Saving');

    answer({ ok: true, message: '', data: saved });

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved'));
    expect(onSaved).toHaveBeenCalledWith(saved);
    expect(byInvitation()).toBeChecked();
    expect(byInvitation()).toHaveFocus();
  });

  it('goes back to the saved value and says the error when the save fails', async () => {
    vi.spyOn(knowiiApiClient.communities, 'updateJoinPolicy').mockResolvedValue({
      ok: false,
      status: 403,
      message: 'Failed to save who can join.',
      errors: {},
    });
    const onSaved = renderSetting({ ...community, join_policy: 'open' });

    await userEvent.click(byInvitation());

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Failed to save who can join.'));
    expect(open()).toBeChecked();
    expect(onSaved).not.toHaveBeenCalled();
  });
});
