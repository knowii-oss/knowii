import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Page } from '@knowii/common';
import { AppDataProvider, KEPT_TEXT_WRITE_INTERVAL_MS, knowiiApiClient, readKeptText, writeKeptText } from '@knowii/common';
import type { PageEditorMode } from './PageEditor';
import { PageEditor } from './PageEditor';

vi.mock('@inertiajs/react', () => ({
  router: { reload: vi.fn(), visit: vi.fn() },
  usePage: () => ({ props: { auth: { user: { cuid: 'ada1' } } } }),
}));

const UPDATED_AT = '2026-11-17T09:00:00.000Z';
const LATER = '2026-11-18T09:00:00.000Z';

function existingPage(updatedAt = UPDATED_AT): Page {
  return {
    cuid: 'p1',
    kind: 'page',
    title: 'Install Obsidian',
    slug: 'install-obsidian',
    position: 0,
    body: 'The text on the server',
    // Inertia props hold ISO strings (front-end rule 6)
    created_at: UPDATED_AT as unknown as Date,
    updated_at: updatedAt as unknown as Date,
  };
}

const onThePage = { account: 'ada1', kind: 'page', place: 'p1' } as const;
const inTheSpace = { account: 'ada1', kind: 'page', place: 's1' } as const;

function keepPageText(fields: Record<string, string>, base: string | null, place: typeof onThePage | typeof inTheSpace = onThePage) {
  writeKeptText(place, { fields, changedAt: Date.UTC(2026, 10, 17, 10, 0, 0), base: base === null ? null : new Date(base).getTime() });
}

function EditorHarness({ page, mode = 'page', onClose }: { page?: Page; mode?: PageEditorMode; onClose: () => void }) {
  const [open, setOpen] = useState(true);
  return (
    <AppDataProvider>
      <PageEditor
        open={open}
        communityCuid="c1"
        spaceCuid="s1"
        spaceName="Guides"
        mode={mode}
        page={page}
        onClose={() => {
          setOpen(false);
          onClose();
        }}
        onSaved={vi.fn()}
      />
    </AppDataProvider>
  );
}

const waitForWrite = () => new Promise((resolve) => setTimeout(resolve, KEPT_TEXT_WRITE_INTERVAL_MS + 200));

describe('the page editor keeps the text that was not saved (#1622)', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    window.localStorage.clear();
  });

  it('keeps the text of a new lesson for the space, with the video address', async () => {
    render(<EditorHarness mode="lesson" onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'New lesson in Guides' });

    await userEvent.type(within(dialog).getByLabelText('Title'), 'First steps');
    await userEvent.type(within(dialog).getByLabelText('Video (optional)'), 'https://vimeo.com/1');

    await waitFor(() =>
      expect(readKeptText(inTheSpace)?.fields).toEqual({ title: 'First steps', body: '', video_url: 'https://vimeo.com/1' }),
    );
  });

  it('keeps the text of an edit for the page, and nothing while the page did not change', async () => {
    render(<EditorHarness page={existingPage()} onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'Edit the page' });
    await waitForWrite();
    expect(window.localStorage.length).toBe(0);

    await userEvent.type(within(dialog).getByLabelText('Title'), ' on Linux');

    await waitFor(() => expect(readKeptText(onThePage)?.fields['title']).toBe('Install Obsidian on Linux'));
    expect(readKeptText(onThePage)?.base).toBe(new Date(UPDATED_AT).getTime());
  });

  it('keeps nothing for a section', async () => {
    render(<EditorHarness mode="section" onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'New section in Guides' });

    await userEvent.type(within(dialog).getByLabelText('Title'), 'Part one');
    await waitForWrite();

    expect(window.localStorage.length).toBe(0);
  });

  it('shows the kept text of an edit when the page did not change on the server after it', async () => {
    keepPageText({ title: 'Install Obsidian', body: 'My new text', video_url: '' }, UPDATED_AT);
    render(<EditorHarness page={existingPage()} onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'Edit the page' });

    expect(within(dialog).getByRole('textbox', { name: 'Text' })).toHaveValue('My new text');
    expect(within(dialog).getByText('We kept the text that you did not send.')).toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: 'Use my text' })).toBeNull();
  });

  it('does not put the kept text in the form when the page changed after it, and offers "Use my text"', async () => {
    keepPageText({ title: 'Install Obsidian', body: 'My new text', video_url: '' }, UPDATED_AT);
    render(<EditorHarness page={existingPage(LATER)} onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'Edit the page' });

    const body = within(dialog).getByRole('textbox', { name: 'Text' });
    expect(body).toHaveValue('The text on the server');
    const line = within(dialog).getByText(/^You have unsent text from .+\. The page changed after that\.$/);
    expect(line).toHaveTextContent('Nov 17, 2026');
    await waitFor(() => expect(dialog).toHaveAccessibleDescription(line.textContent ?? ''));

    await userEvent.click(within(dialog).getByRole('button', { name: 'Use my text' }));

    expect(body).toHaveValue('My new text');
    expect(within(dialog).getByLabelText('Title')).toHaveFocus();
    expect(within(dialog).getByText('We kept the text that you did not send.')).toBeInTheDocument();
    expect(readKeptText(onThePage)).not.toBeNull();
  });

  it('keeps the old text while the person did not choose, also after a change and Escape', async () => {
    keepPageText({ title: 'Install Obsidian', body: 'My new text', video_url: '' }, UPDATED_AT);
    const onClose = vi.fn();
    render(<EditorHarness page={existingPage(LATER)} onClose={onClose} />);
    const dialog = await screen.findByRole('dialog', { name: 'Edit the page' });

    await userEvent.type(within(dialog).getByLabelText('Title'), '!');
    await waitForWrite();
    expect(readKeptText(onThePage)?.fields['body']).toBe('My new text');

    await userEvent.keyboard('{Escape}');
    const question = await screen.findByRole('dialog', { name: 'Discard your text?' });
    await userEvent.click(within(question).getByRole('button', { name: 'Discard' }));

    await waitFor(() => expect(onClose).toHaveBeenCalledOnce());
    expect(readKeptText(onThePage)?.fields['body']).toBe('My new text');
  });

  it('removes the kept text with "Discard" when the page changed, and the form keeps the page', async () => {
    keepPageText({ title: 'Install Obsidian', body: 'My new text', video_url: '' }, UPDATED_AT);
    render(<EditorHarness page={existingPage(LATER)} onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'Edit the page' });

    await userEvent.click(within(dialog).getByRole('button', { name: 'Discard' }));

    expect(within(dialog).getByRole('textbox', { name: 'Text' })).toHaveValue('The text on the server');
    expect(within(dialog).getByLabelText('Title')).toHaveFocus();
    expect(within(dialog).queryByText(/You have unsent text/)).toBeNull();
    expect(readKeptText(onThePage)).toBeNull();
  });

  it('removes the kept text after a save', async () => {
    keepPageText({ title: 'Install Obsidian', body: 'My new text', video_url: '' }, UPDATED_AT);
    const update = vi
      .spyOn(knowiiApiClient.communities.pages, 'update')
      .mockResolvedValue({ ok: true, message: '', data: existingPage(LATER) });
    render(<EditorHarness page={existingPage()} onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'Edit the page' });

    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 's1', 'p1', { title: 'Install Obsidian', body: 'My new text' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(readKeptText(onThePage)).toBeNull();
  });
});
