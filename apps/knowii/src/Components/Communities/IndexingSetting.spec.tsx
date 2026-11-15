import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community } from '@knowii/common';
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

function renderSetting(value: Community = community, onSaved = vi.fn()) {
  render(
    <AppDataProvider>
      <IndexingSetting community={value} onSaved={onSaved} />
    </AppDataProvider>,
  );
  return onSaved;
}

describe('IndexingSetting (#1487)', () => {
  it('is off by default, and says what it does and that it is off by default', () => {
    renderSetting();

    const box = screen.getByRole('checkbox', { name: /Search engines may index the public spaces/ });
    expect(box).not.toBeChecked();
    expect(box).toHaveAccessibleDescription(/Off by default\..*may appear in search engines/);
  });

  it('is off when the community does not tell', () => {
    renderSetting({ ...community, indexing_enabled: undefined });

    expect(screen.getByRole('checkbox', { name: /Search engines may index/ })).not.toBeChecked();
  });

  it('shows the saved value', () => {
    renderSetting({ ...community, indexing_enabled: true });

    expect(screen.getByRole('checkbox', { name: /Search engines may index/ })).toBeChecked();
  });

  it('saves a change at once, with the keyboard', async () => {
    const saved = { ...community, indexing_enabled: true } as Community;
    const update = vi.spyOn(knowiiApiClient.communities, 'updateIndexing').mockResolvedValue({ ok: true, message: '', data: saved });
    const onSaved = renderSetting();

    screen.getByRole('checkbox', { name: /Search engines may index/ }).focus();
    await userEvent.keyboard(' ');

    await waitFor(() => expect(update).toHaveBeenCalledWith('c1', true));
    await waitFor(() => expect(onSaved).toHaveBeenCalledWith(saved));
  });

  it('goes back to the saved value when the save fails', async () => {
    vi.spyOn(knowiiApiClient.communities, 'updateIndexing').mockResolvedValue({ ok: false, status: 403, message: 'No', errors: {} });
    const onSaved = renderSetting();

    await userEvent.click(screen.getByRole('checkbox', { name: /Search engines may index/ }));

    await waitFor(() => expect(screen.getByRole('checkbox', { name: /Search engines may index/ })).not.toBeChecked());
    expect(onSaved).not.toHaveBeenCalled();
  });
});
