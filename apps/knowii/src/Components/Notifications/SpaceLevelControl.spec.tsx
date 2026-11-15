import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import { spyOnFollowApi } from '@/test-utils/notifications';
import { SpaceLevelControl } from './SpaceLevelControl';

describe('the level of a space for the member (#1497)', () => {
  let api: ReturnType<typeof spyOnFollowApi>;

  beforeEach(() => {
    api = spyOnFollowApi();
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  const renderControl = (spaceType = 'discussion') => render(<SpaceLevelControl communityCuid="c1" spaceCuid="s1" spaceType={spaceType} />);

  it('names the level of the member on its button, and opens the three choices with their sentences', async () => {
    renderControl();
    const user = userEvent.setup();

    const button = await screen.findByRole('button', { name: 'Notifications: Only what is for me' });
    expect(button).toHaveAttribute('aria-expanded', 'false');
    await user.click(button);

    const group = screen.getByRole('group', { name: 'Notifications for this space' });
    expect(
      within(group)
        .getAllByRole('radio')
        .map((radio) => radio.closest('label')?.textContent),
    ).toEqual([
      'EverythingYou get a line for every new post here. In a chat space the lines are bundled, one per space.',
      'Only what is for meYou are told when someone replies to you, mentions you, or writes in a thread that you follow.',
      'MutedYou will get nothing from this space, also when someone mentions you.',
    ]);
    expect(within(group).getByRole('radio', { name: /^Only what is for me/ })).toBeChecked();
  });

  it('offers Everything in a chat space too', async () => {
    renderControl('chat');
    await userEvent.click(await screen.findByRole('button', { name: /^Notifications:/ }));

    expect(screen.getByRole('radio', { name: /^Everything/ })).toBeInTheDocument();
  });

  it('offers no Everything in a course', async () => {
    renderControl('course');
    await userEvent.click(await screen.findByRole('button', { name: /^Notifications:/ }));

    expect(screen.queryByRole('radio', { name: /^Everything/ })).toBeNull();
    expect(screen.getAllByRole('radio')).toHaveLength(2);
  });

  it('shows nothing for a link space', async () => {
    const { container } = renderControl('link');

    await waitFor(() => expect(api.getLevel).toHaveBeenCalled());
    expect(container).toBeEmptyDOMElement();
  });

  it('saves a choice at once and says "Saved" in a status region', async () => {
    renderControl();
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: /^Notifications:/ }));

    await user.click(screen.getByRole('radio', { name: /^Muted/ }));

    expect(api.setLevel).toHaveBeenCalledWith('c1', 's1', 'none');
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved'));
    expect(screen.getByRole('button', { name: 'Notifications: Muted' })).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: /^Muted/ })).toBeChecked();
  });

  it('keeps the old choice on an error, with an alert and the focus on the choice', async () => {
    api.setLevel.mockResolvedValue({ ok: false, status: 500, message: 'Failed', errors: {} });
    renderControl();
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: /^Notifications:/ }));
    const everything = screen.getByRole('radio', { name: /^Everything/ });

    await user.click(everything);

    expect(await screen.findByRole('alert')).toHaveTextContent('Not saved. Retry.');
    expect(screen.getByRole('radio', { name: /^Only what is for me/ })).toBeChecked();
    expect(everything).toHaveFocus();
    expect(screen.queryByText('Saved')).toBeNull();
  });

  it('has no automated violation (axe), open', async () => {
    const { container } = renderControl();
    await userEvent.click(await screen.findByRole('button', { name: /^Notifications:/ }));

    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } });

    expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.html).join(' | ')}`)).toEqual([]);
  });
});
