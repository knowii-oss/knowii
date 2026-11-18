import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ApiResult, Community } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { IndexingSetting } from './IndexingSetting';

const community = {
  cuid: 'c1',
  name: 'Tests',
  slug: 'tests',
  description: '',
  visibility: 'private',
  indexing_enabled: false,
  created_at: new Date(),
  updated_at: new Date(),
} as Community;

const QUESTION = 'Let search engines index this community?';

function renderSetting(value: Community = community, onSaved = vi.fn()) {
  render(
    <AppDataProvider>
      <IndexingSetting community={value} onSaved={onSaved} />
    </AppDataProvider>,
  );
  return onSaved;
}

const indexingSwitch = () => screen.getByRole('switch', { name: /Search engines may index the public spaces/ });

afterEach(() => {
  vi.restoreAllMocks();
});

describe('IndexingSetting (#1487, #1569)', () => {
  it('is a switch, off by default, that says what it does and that it is off by default', () => {
    renderSetting();

    const toggle = indexingSwitch();
    expect(toggle).not.toBeChecked();
    expect(toggle).toHaveAccessibleDescription(/Off by default\..*may appear in search engines/);
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('');
  });

  it('is off when the community does not tell', () => {
    renderSetting({ ...community, indexing_enabled: undefined });

    expect(indexingSwitch()).not.toBeChecked();
  });

  it('shows the saved value', () => {
    renderSetting({ ...community, indexing_enabled: true });

    expect(indexingSwitch()).toBeChecked();
  });

  it('turns indexing off at once, without a question: "Saving", then "Saved", and the switch keeps the focus', async () => {
    const saved = { ...community, indexing_enabled: false } as Community;
    let answer: (result: ApiResult<Community>) => void = () => undefined;
    const update = vi
      .spyOn(knowiiApiClient.communities, 'updateIndexing')
      .mockReturnValue(new Promise<ApiResult<Community>>((resolve) => (answer = resolve)));
    const onSaved = renderSetting({ ...community, indexing_enabled: true });

    indexingSwitch().focus();
    await userEvent.keyboard(' ');

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(update).toHaveBeenCalledWith('c1', false);
    expect(indexingSwitch()).not.toBeChecked();
    expect(indexingSwitch()).toHaveAttribute('aria-busy', 'true');
    expect(screen.getByRole('status')).toHaveTextContent('Saving');

    answer({ ok: true, message: '', data: saved });

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved'));
    expect(onSaved).toHaveBeenCalledWith(saved);
    expect(indexingSwitch()).not.toBeChecked();
    expect(indexingSwitch()).toHaveAttribute('aria-busy', 'false');
    expect(indexingSwitch()).toHaveFocus();
  });

  it('asks before it turns indexing on, and saves nothing before the yes', async () => {
    const update = vi.spyOn(knowiiApiClient.communities, 'updateIndexing');
    renderSetting();

    indexingSwitch().focus();
    await userEvent.keyboard(' ');

    const dialog = await screen.findByRole('dialog', { name: QUESTION });
    expect(dialog).toHaveAccessibleDescription(
      'Search engines can then show the public page and the public spaces of this community in their results. If you turn it off later, search engines need time to forget the pages.',
    );
    expect(within(dialog).getByRole('button', { name: 'Allow indexing' })).toBeInTheDocument();
    await waitFor(() => expect(within(dialog).getByRole('button', { name: 'Cancel' })).toHaveFocus());
    expect(update).not.toHaveBeenCalled();
    expect(indexingSwitch()).not.toBeChecked();
  });

  it('"Cancel" leaves indexing off and gives the focus back to the switch', async () => {
    const update = vi.spyOn(knowiiApiClient.communities, 'updateIndexing');
    const onSaved = renderSetting();

    indexingSwitch().focus();
    await userEvent.keyboard(' ');
    await userEvent.click(within(await screen.findByRole('dialog', { name: QUESTION })).getByRole('button', { name: 'Cancel' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await waitFor(() => expect(indexingSwitch()).toHaveFocus());
    expect(indexingSwitch()).not.toBeChecked();
    expect(update).not.toHaveBeenCalled();
    expect(onSaved).not.toHaveBeenCalled();
    expect(screen.getByRole('status')).toHaveTextContent('');
  });

  it('Escape leaves indexing off and gives the focus back to the switch', async () => {
    const update = vi.spyOn(knowiiApiClient.communities, 'updateIndexing');
    renderSetting();

    indexingSwitch().focus();
    await userEvent.keyboard(' ');
    await screen.findByRole('dialog', { name: QUESTION });
    await userEvent.keyboard('{Escape}');

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await waitFor(() => expect(indexingSwitch()).toHaveFocus());
    expect(indexingSwitch()).not.toBeChecked();
    expect(update).not.toHaveBeenCalled();
  });

  it('"Allow indexing" saves, says "Saved", and gives the focus back to the switch', async () => {
    const saved = { ...community, indexing_enabled: true } as Community;
    const update = vi.spyOn(knowiiApiClient.communities, 'updateIndexing').mockResolvedValue({ ok: true, message: '', data: saved });
    const onSaved = renderSetting();

    await userEvent.click(indexingSwitch());
    await userEvent.click(within(await screen.findByRole('dialog', { name: QUESTION })).getByRole('button', { name: 'Allow indexing' }));

    expect(update).toHaveBeenCalledWith('c1', true);
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved'));
    expect(onSaved).toHaveBeenCalledWith(saved);
    expect(indexingSwitch()).toBeChecked();
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await waitFor(() => expect(indexingSwitch()).toHaveFocus());
  });

  it('goes back to the saved value and says the error when the save fails', async () => {
    vi.spyOn(knowiiApiClient.communities, 'updateIndexing').mockResolvedValue({
      ok: false,
      status: 403,
      message: 'Failed to save the indexing setting.',
      errors: {},
    });
    const onSaved = renderSetting({ ...community, indexing_enabled: true });

    await userEvent.click(indexingSwitch());

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Failed to save the indexing setting.'));
    expect(indexingSwitch()).toBeChecked();
    expect(indexingSwitch()).toHaveFocus();
    expect(onSaved).not.toHaveBeenCalled();
  });

  it('follows the saved value when the page loads the community again', () => {
    const { rerender } = render(
      <AppDataProvider>
        <IndexingSetting community={community} />
      </AppDataProvider>,
    );

    rerender(
      <AppDataProvider>
        <IndexingSetting community={{ ...community, indexing_enabled: true }} />
      </AppDataProvider>,
    );

    expect(indexingSwitch()).toBeChecked();
  });
});
