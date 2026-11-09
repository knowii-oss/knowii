import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MarkdownEditor } from './MarkdownEditor';

function Harness({ renderPreview }: { renderPreview: (markdown: string, signal: AbortSignal) => Promise<string | null> }) {
  const [value, setValue] = useState('');
  return (
    <>
      <label htmlFor="body">Body</label>
      <MarkdownEditor id="body" value={value} onChange={setValue} renderPreview={renderPreview} />
    </>
  );
}

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
});
