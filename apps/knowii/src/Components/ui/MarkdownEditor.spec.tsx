import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { MentionSource } from './MarkdownEditor';
import { findMentionQuery, MarkdownEditor } from './MarkdownEditor';

function Harness({
  renderPreview,
  mentions,
}: {
  renderPreview: (markdown: string, signal: AbortSignal) => Promise<string | null>;
  mentions?: MentionSource;
}) {
  const [value, setValue] = useState('');
  return (
    <>
      <label htmlFor="body">Body</label>
      <MarkdownEditor id="body" value={value} onChange={setValue} renderPreview={renderPreview} mentions={mentions} />
    </>
  );
}

describe('findMentionQuery', () => {
  it('finds the username being typed after an @', () => {
    expect(findMentionQuery('Hi @ad', 6)).toEqual({ start: 3, query: 'ad' });
    expect(findMentionQuery('@', 1)).toEqual({ start: 0, query: '' });
    expect(findMentionQuery('(@grace', 7)).toEqual({ start: 1, query: 'grace' });
  });

  it('ignores email addresses and finished mentions', () => {
    expect(findMentionQuery('me@ad', 5)).toBeNull();
    expect(findMentionQuery('@ada done', 9)).toBeNull();
  });
});

describe('MarkdownEditor', () => {
  it('formats the text with a keyboard shortcut and with the toolbar', async () => {
    render(<Harness renderPreview={vi.fn()} />);
    const textarea = screen.getByRole('textbox', { name: 'Body' });

    await userEvent.type(textarea, 'hi');
    textarea.focus();
    (textarea as HTMLTextAreaElement).setSelectionRange(0, 2);
    await userEvent.keyboard('{Control>}b{/Control}');
    expect(textarea).toHaveValue('**hi**');

    await userEvent.click(screen.getByRole('button', { name: 'Italic (Ctrl+I)' }));
    expect(textarea).toHaveValue('**_hi_**');
    expect(screen.getByRole('toolbar', { name: 'Formatting' })).toBeInTheDocument();
  });

  it('switches to the preview with the arrow keys and shows the rendered HTML', async () => {
    const renderPreview = vi.fn().mockResolvedValue('<p><strong>hi</strong></p>');
    render(<Harness renderPreview={renderPreview} />);
    await userEvent.type(screen.getByRole('textbox', { name: 'Body' }), '**hi**');

    const writeTab = screen.getByRole('tab', { name: 'Write' });
    expect(writeTab).toHaveAttribute('aria-selected', 'true');
    writeTab.focus();
    await userEvent.keyboard('{ArrowRight}');

    const previewTab = screen.getByRole('tab', { name: 'Preview' });
    expect(previewTab).toHaveAttribute('aria-selected', 'true');
    expect(previewTab).toHaveFocus();
    await waitFor(() => expect(screen.getByRole('tabpanel', { name: 'Preview' })).toContainHTML('<strong>hi</strong>'));
    expect(renderPreview).toHaveBeenCalledWith('**hi**', expect.any(AbortSignal));
    expect(screen.queryByRole('toolbar')).not.toBeInTheDocument();
  });

  it('says when the preview fails', async () => {
    render(<Harness renderPreview={vi.fn().mockResolvedValue(null)} />);
    await userEvent.type(screen.getByRole('textbox', { name: 'Body' }), 'x');
    await userEvent.click(screen.getByRole('tab', { name: 'Preview' }));

    expect(await screen.findByText(/The preview is not available/)).toBeInTheDocument();
  });

  it('suggests members after @ and inserts the chosen one with the keyboard', async () => {
    const search = vi.fn().mockResolvedValue([
      { username: 'ada', name: 'Ada Lovelace' },
      { username: 'adam', name: 'Adam Smith' },
    ]);
    render(<Harness renderPreview={vi.fn()} mentions={{ search }} />);
    const textarea = screen.getByRole('combobox', { name: 'Body' });
    expect(textarea).toHaveAttribute('aria-expanded', 'false');

    await userEvent.type(textarea, 'Thanks @ad');
    const listbox = await screen.findByRole('listbox', { name: 'Members to mention' });
    expect(search).toHaveBeenCalledWith('ad', expect.any(AbortSignal));
    expect(textarea).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByRole('option', { name: /@ada Ada Lovelace/ })).toHaveAttribute('aria-selected', 'true');

    await userEvent.keyboard('{ArrowDown}');
    expect(screen.getByRole('option', { name: /@adam/ })).toHaveAttribute('aria-selected', 'true');
    expect(textarea).toHaveAttribute('aria-activedescendant', `${listbox.id}-1`);

    await userEvent.keyboard('{Enter}');
    expect(textarea).toHaveValue('Thanks @adam ');
    expect(listbox).not.toBeVisible();
  });

  it('closes the suggestions with Escape', async () => {
    render(<Harness renderPreview={vi.fn()} mentions={{ search: vi.fn().mockResolvedValue([{ username: 'ada', name: 'Ada' }]) }} />);
    const textarea = screen.getByRole('combobox', { name: 'Body' });
    await userEvent.type(textarea, '@a');
    await screen.findByRole('option');

    await userEvent.keyboard('{Escape}');
    expect(textarea).toHaveAttribute('aria-expanded', 'false');
    expect(textarea).toHaveValue('@a');
  });
});
