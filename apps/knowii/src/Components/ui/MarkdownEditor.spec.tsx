import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ImageUploader, MentionSource } from './MarkdownEditor';
import { findMentionQuery, MarkdownEditor } from './MarkdownEditor';

function Harness({
  renderPreview,
  mentions,
  images,
  initial = '',
}: {
  renderPreview: (markdown: string, signal: AbortSignal) => Promise<string | null>;
  mentions?: MentionSource;
  images?: ImageUploader;
  initial?: string;
}) {
  const [value, setValue] = useState(initial);
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

  it('clears the timer of the mention list when the editor goes away (#1554)', () => {
    vi.useFakeTimers();
    try {
      const { unmount } = render(<Harness renderPreview={vi.fn()} />);
      fireEvent.blur(screen.getByLabelText('Body'));
      expect(vi.getTimerCount()).toBe(1);

      unmount();
      expect(vi.getTimerCount()).toBe(0);
    } finally {
      vi.useRealTimers();
    }
  });

  it('starts one timer for two blurs in a row', () => {
    vi.useFakeTimers();
    try {
      render(<Harness renderPreview={vi.fn()} />);
      const textarea = screen.getByLabelText('Body');
      fireEvent.blur(textarea);
      fireEvent.blur(textarea);
      expect(vi.getTimerCount()).toBe(1);
    } finally {
      vi.useRealTimers();
    }
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

  const png = (name: string) => new File(['png'], name, { type: 'image/png' });
  const uploader = (upload: ImageUploader['upload']): ImageUploader => ({ upload, maxSizeBytes: 1000, acceptedTypes: ['image/png'] });
  const mockPreviews = () => {
    let count = 0;
    URL.createObjectURL = vi.fn(() => `blob:preview-${++count}`);
    URL.revokeObjectURL = vi.fn();
  };

  it('takes several images at a time, each with its description, and writes them one per line (#1207)', async () => {
    mockPreviews();
    const upload = vi.fn((file: File) => Promise.resolve({ url: `/storage/uploads/${file.name.replace('.png', '.webp')}` }));
    const { container } = render(<Harness renderPreview={vi.fn()} images={uploader(upload)} />);
    await userEvent.type(screen.getByRole('textbox', { name: 'Body' }), 'Our trip:');

    await userEvent.upload(container.querySelector('input[type=file]') as HTMLInputElement, [png('a.png'), png('b.png'), png('c.png')]);

    const panel = screen.getByRole('group', { name: 'Add 3 images' });
    const first = screen.getByRole('textbox', { name: 'Description of image 1 of 3 (alt text)' });
    await waitFor(() => expect(first).toHaveFocus());

    // Without every description, nothing is uploaded, and the focus goes to the first one that is missing
    await userEvent.type(first, 'The harbour');
    await userEvent.click(screen.getByRole('button', { name: 'Insert the 3 images' }));
    expect(screen.getAllByText('Describe the image for people who cannot see it.')).toHaveLength(2);
    expect(upload).not.toHaveBeenCalled();
    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Description of image 2 of 3 (alt text)' })).toHaveFocus());

    await userEvent.type(screen.getByRole('textbox', { name: 'Description of image 2 of 3 (alt text)' }), 'The market');
    await userEvent.type(screen.getByRole('textbox', { name: 'Description of image 3 of 3 (alt text)' }), 'The beach');

    // The beach goes first: the arrow button moves it and keeps the focus
    await userEvent.click(screen.getByRole('button', { name: 'Move image 3 up' }));
    expect(screen.getByRole('button', { name: 'Move image 2 up' })).toHaveFocus();
    await userEvent.click(screen.getByRole('button', { name: 'Move image 2 up' }));
    const top = screen.getByRole('button', { name: 'Move image 1 up' });
    expect(top).toHaveFocus();
    expect(top).toHaveAttribute('aria-disabled', 'true');
    expect(screen.getByRole('textbox', { name: 'Description of image 1 of 3 (alt text)' })).toHaveValue('The beach');

    await userEvent.click(screen.getByRole('button', { name: 'Insert the 3 images' }));

    await waitFor(() => expect(panel).not.toBeInTheDocument());
    expect(upload.mock.calls.map(([file, alt]) => [file.name, alt])).toEqual([
      ['c.png', 'The beach'],
      ['a.png', 'The harbour'],
      ['b.png', 'The market'],
    ]);
    expect(screen.getByRole('textbox', { name: 'Body' })).toHaveValue(
      'Our trip:\n![The beach](/storage/uploads/c.webp)\n![The harbour](/storage/uploads/a.webp)\n![The market](/storage/uploads/b.webp)\n',
    );
  });

  it('keeps 10 images per insert, lets the member remove one, and goes back to the short form for one image (#1207)', async () => {
    mockPreviews();
    const { container } = render(<Harness renderPreview={vi.fn()} images={uploader(vi.fn())} />);
    const input = container.querySelector('input[type=file]') as HTMLInputElement;

    await userEvent.upload(
      input,
      Array.from({ length: 12 }, (_, index) => png(`${index}.png`)),
    );
    expect(screen.getByRole('group', { name: 'Add 10 images' })).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent('You can add 10 images at a time: the others were left out.');

    for (let count = 10; count > 1; count--) {
      await userEvent.click(screen.getByRole('button', { name: `Remove image ${count}` }));
    }
    expect(screen.getByRole('group', { name: 'Add an image' })).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Image description (alt text)' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Move image 1 up' })).toBeNull();

    // More images join the ones that wait
    await userEvent.upload(input, png('more.png'));
    expect(screen.getByRole('group', { name: 'Add 2 images' })).toBeInTheDocument();
  });

  it('keeps the images when an upload fails, and uploads only the failed one again (#1207)', async () => {
    mockPreviews();
    const upload = vi
      .fn()
      .mockResolvedValueOnce({ url: '/storage/uploads/a.webp' })
      .mockResolvedValueOnce({ error: 'The image could not be read.' })
      .mockResolvedValueOnce({ url: '/storage/uploads/b.webp' });
    const { container } = render(<Harness renderPreview={vi.fn()} images={uploader(upload)} />);

    await userEvent.upload(container.querySelector('input[type=file]') as HTMLInputElement, [png('a.png'), png('b.png')]);
    // The editor gives the focus to the first description a moment after the files are chosen
    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Description of image 1 of 2 (alt text)' })).toHaveFocus());
    await userEvent.type(screen.getByRole('textbox', { name: 'Description of image 1 of 2 (alt text)' }), 'First');
    await userEvent.type(screen.getByRole('textbox', { name: 'Description of image 2 of 2 (alt text)' }), 'Second');
    await userEvent.click(screen.getByRole('button', { name: 'Insert the 2 images' }));

    expect(await screen.findByText('The image could not be read.')).toBeInTheDocument();
    expect(screen.getByText('Uploaded')).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Body' })).toHaveValue('');

    await userEvent.click(screen.getByRole('button', { name: 'Insert the 2 images' }));
    await waitFor(() =>
      expect(screen.getByRole('textbox', { name: 'Body' })).toHaveValue(
        '![First](/storage/uploads/a.webp)\n![Second](/storage/uploads/b.webp)\n',
      ),
    );
    expect(upload).toHaveBeenCalledTimes(3);
  });

  it('refuses more images than a text can hold (#1207)', async () => {
    mockPreviews();
    const upload = vi.fn();
    const initial = Array.from({ length: 19 }, (_, index) => `![Image ${index}](/storage/uploads/${index}.webp)`).join('\n');
    const { container } = render(<Harness renderPreview={vi.fn()} images={uploader(upload)} initial={initial} />);

    await userEvent.upload(container.querySelector('input[type=file]') as HTMLInputElement, [png('a.png'), png('b.png')]);
    // The editor gives the focus to the first description a moment after the files are chosen
    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Description of image 1 of 2 (alt text)' })).toHaveFocus());
    await userEvent.type(screen.getByRole('textbox', { name: 'Description of image 1 of 2 (alt text)' }), 'First');
    await userEvent.type(screen.getByRole('textbox', { name: 'Description of image 2 of 2 (alt text)' }), 'Second');
    await userEvent.click(screen.getByRole('button', { name: 'Insert the 2 images' }));

    expect(screen.getByText('A text holds 20 images at most: there is room for 1 more. Remove 1.')).toBeInTheDocument();
    expect(upload).not.toHaveBeenCalled();
  });

  it('keeps the text that the member writes while the images upload (#1207)', async () => {
    mockPreviews();
    let finish: (result: { url: string }) => void = () => undefined;
    const upload = vi.fn(() => new Promise<{ url: string }>((resolve) => (finish = resolve)));
    const { container } = render(<Harness renderPreview={vi.fn()} images={uploader(upload)} initial="Before" />);
    const body = screen.getByRole('textbox', { name: 'Body' });

    await userEvent.upload(container.querySelector('input[type=file]') as HTMLInputElement, png('a.png'));
    const alt = screen.getByRole('textbox', { name: 'Image description (alt text)' });
    await waitFor(() => expect(alt).toHaveFocus());
    await userEvent.type(alt, 'A chart{Enter}');
    await waitFor(() => expect(upload).toHaveBeenCalledTimes(1));

    // The upload runs: the member goes on with the text
    await userEvent.type(body, ' and after', { initialSelectionStart: 6 });
    expect(body).toHaveValue('Before and after');
    finish({ url: '/storage/uploads/a.webp' });

    await waitFor(() => expect(body).toHaveValue('Before and after\n![A chart](/storage/uploads/a.webp)\n'));
  });

  it('keeps a dropped file that is not an image out of the browser, and cancels the images with Escape (#1207)', async () => {
    mockPreviews();
    render(<Harness renderPreview={vi.fn()} images={uploader(vi.fn())} />);
    const body = screen.getByRole('textbox', { name: 'Body' });

    // Without preventDefault, the browser opens the file in place of the page
    const pdf = new File(['pdf'], 'notes.pdf', { type: 'application/pdf' });
    expect(fireEvent.drop(body, { dataTransfer: { files: [pdf], types: ['Files'] } })).toBe(false);
    expect(screen.getByRole('alert')).toHaveTextContent('Choose a JPEG, PNG, WebP or GIF image.');

    fireEvent.drop(body, { dataTransfer: { files: [png('a.png'), png('b.png')], types: ['Files'] } });
    expect(screen.getByRole('group', { name: 'Add 2 images' })).toBeInTheDocument();

    // Escape on a button of the panel cancels the images and stays in the editor (a dialog around it stays open)
    const onDocumentKeyDown = vi.fn();
    document.addEventListener('keydown', onDocumentKeyDown);
    screen.getByRole('button', { name: 'Move image 2 up' }).focus();
    await userEvent.keyboard('{Escape}');
    document.removeEventListener('keydown', onDocumentKeyDown);

    expect(screen.queryByRole('group', { name: 'Add 2 images' })).toBeNull();
    expect(onDocumentKeyDown).not.toHaveBeenCalled();
    expect(body).toHaveFocus();
  });

  it('adds a YouTube or Vimeo video alone on its line, and refuses other addresses (#1206)', async () => {
    render(<Harness renderPreview={vi.fn().mockResolvedValue('')} />);
    const textarea = screen.getByRole('textbox', { name: 'Body' });
    await userEvent.type(textarea, 'Watch this:');

    await userEvent.click(screen.getByRole('button', { name: 'Add a video' }));
    const address = screen.getByRole('textbox', { name: 'Video address (YouTube or Vimeo)' });
    expect(address).toHaveFocus();

    await userEvent.type(address, 'https://example.com/video{Enter}');
    expect(screen.getByRole('alert')).toHaveTextContent('Enter the address of a YouTube or Vimeo video.');

    await userEvent.clear(address);
    await userEvent.type(address, 'https://youtu.be/dQw4w9WgXcQ{Enter}');

    expect(screen.queryByRole('group', { name: 'Add a video' })).not.toBeInTheDocument();
    expect(textarea).toHaveValue('Watch this:\n\nhttps://youtu.be/dQw4w9WgXcQ\n');
    await waitFor(() => expect(textarea).toHaveFocus());
  });

  it('cancels the video with Escape and gives the focus back to the text (#1206)', async () => {
    render(<Harness renderPreview={vi.fn().mockResolvedValue('')} />);

    await userEvent.click(screen.getByRole('button', { name: 'Add a video' }));
    await userEvent.keyboard('{Escape}');

    expect(screen.queryByRole('group', { name: 'Add a video' })).not.toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Body' })).toHaveFocus();
  });
});
