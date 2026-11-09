import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ImageUploader, MentionSource } from './MarkdownEditor';
import { findMentionQuery, MarkdownEditor } from './MarkdownEditor';

function Harness({
  renderPreview,
  mentions,
  images,
}: {
  renderPreview: (markdown: string, signal: AbortSignal) => Promise<string | null>;
  mentions?: MentionSource;
  images?: ImageUploader;
}) {
  const [value, setValue] = useState('');
  return (
    <>
      <label htmlFor="body">Body</label>
      <MarkdownEditor id="body" value={value} onChange={setValue} renderPreview={renderPreview} mentions={mentions} images={images} />
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

  it('asks for the alt text, uploads the image and inserts it', async () => {
    URL.createObjectURL = vi.fn(() => 'blob:preview');
    URL.revokeObjectURL = vi.fn();
    const upload = vi.fn().mockResolvedValue({ url: '/storage/uploads/a.webp' });
    const images: ImageUploader = { upload, maxSizeBytes: 1000, acceptedTypes: ['image/png'] };
    const { container } = render(<Harness renderPreview={vi.fn()} images={images} />);
    await userEvent.type(screen.getByRole('textbox', { name: 'Body' }), 'Look:');

    const file = new File(['png'], 'shot.png', { type: 'image/png' });
    await userEvent.upload(container.querySelector('input[type=file]') as HTMLInputElement, file);

    const panel = screen.getByRole('group', { name: 'Add an image' });
    const alt = screen.getByRole('textbox', { name: 'Image description (alt text)' });
    await waitFor(() => expect(alt).toHaveFocus());
    await userEvent.click(screen.getByRole('button', { name: 'Insert the image' }));
    expect(await screen.findByText('Describe the image for people who cannot see it.')).toBeInTheDocument();
    expect(upload).not.toHaveBeenCalled();

    await userEvent.type(alt, 'A [[chart]{Enter}');
    expect(upload).toHaveBeenCalledWith(file, 'A [chart]');
    expect(screen.getByRole('textbox', { name: 'Body' })).toHaveValue('Look:\n![A chart](/storage/uploads/a.webp)\n');
    expect(panel).not.toBeInTheDocument();
  });

  it('refuses files of the wrong type or size before uploading', async () => {
    const images: ImageUploader = { upload: vi.fn(), maxSizeBytes: 2, acceptedTypes: ['image/png'] };
    const { container } = render(<Harness renderPreview={vi.fn()} images={images} />);
    const input = container.querySelector('input[type=file]') as HTMLInputElement;

    await userEvent.upload(input, new File(['too big'], 'big.png', { type: 'image/png' }));
    expect(screen.getByRole('alert')).toHaveTextContent(/too large/);
    expect(screen.queryByRole('group', { name: 'Add an image' })).not.toBeInTheDocument();
  });
});
