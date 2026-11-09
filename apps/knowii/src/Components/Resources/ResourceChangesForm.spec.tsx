import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { CommunityResource } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { ResourceChangesForm } from './ResourceChangesForm';

const resource = {
  cuid: 'r1',
  type: 'article',
  type_chosen: false,
  level: 'beginner',
  capture: { markdown: null, word_count: 10, reading_time: 1, source: 'server', captured_at: new Date().toISOString() },
} as unknown as CommunityResource;

describe('ResourceChangesForm', () => {
  it('saves only the changed field, and warns that another type removes the captured text (#1246)', async () => {
    const update = vi.spyOn(knowiiApiClient.resources, 'update').mockResolvedValue({ ok: true, message: '', data: resource });
    const onSaved = vi.fn();
    render(
      <AppDataProvider>
        <ResourceChangesForm communityCuid="c1" spaceCuid="s1" resource={resource} onSaved={onSaved} />
      </AppDataProvider>,
    );
    expect(screen.getByText('Detected from the page.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();

    await userEvent.click(screen.getByLabelText('Type'));
    await userEvent.click(await screen.findByRole('option', { name: 'Video' }));

    expect(screen.getByText(/Only articles keep the captured text/)).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 's1', 'r1', { type: 'video' }));
    expect(onSaved).toHaveBeenCalled();
  });
});
