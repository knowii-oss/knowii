/**
 * Formatting commands of the Markdown editor, as pure functions (tested without a DOM).
 */
export type MarkdownFormat = 'bold' | 'italic' | 'code' | 'link' | 'quote' | 'bulletList' | 'numberedList';

export interface TextSelection {
  value: string;
  selectionStart: number;
  selectionEnd: number;
}

const wrappers: Partial<Record<MarkdownFormat, { before: string; after: string; placeholder: string }>> = {
  bold: { before: '**', after: '**', placeholder: 'bold text' },
  italic: { before: '_', after: '_', placeholder: 'italic text' },
  code: { before: '`', after: '`', placeholder: 'code' },
};

const linePrefixes: Partial<Record<MarkdownFormat, (index: number) => string>> = {
  quote: () => '> ',
  bulletList: () => '- ',
  numberedList: (index) => `${index + 1}. `,
};

/**
 * Apply a format to the selection. Wrapping formats wrap the selection (or a placeholder, selected so the member
 * can type over it). Line formats prefix every selected line. The link format wraps the selection as the text and
 * selects the `url` placeholder.
 */
export function applyMarkdownFormat(input: TextSelection, format: MarkdownFormat): TextSelection {
  const { value, selectionStart, selectionEnd } = input;
  const selected = value.slice(selectionStart, selectionEnd);
  const before = value.slice(0, selectionStart);
  const after = value.slice(selectionEnd);

  const wrapper = wrappers[format];
  if (wrapper) {
    const text = selected || wrapper.placeholder;
    const start = before.length + wrapper.before.length;
    return {
      value: `${before}${wrapper.before}${text}${wrapper.after}${after}`,
      selectionStart: start,
      selectionEnd: start + text.length,
    };
  }

  if (format === 'link') {
    const text = selected || 'link text';
    const urlStart = before.length + text.length + 3;
    return {
      value: `${before}[${text}](url)${after}`,
      selectionStart: urlStart,
      selectionEnd: urlStart + 3,
    };
  }

  const prefix = linePrefixes[format];
  if (!prefix) {
    return input;
  }

  // Extend the selection to whole lines
  const lineStart = before.lastIndexOf('\n') + 1;
  const nextBreak = value.indexOf('\n', selectionEnd);
  const lineEnd = nextBreak === -1 ? value.length : nextBreak;
  const lines = value.slice(lineStart, lineEnd).split('\n');
  const prefixed = lines.map((line, index) => `${prefix(index)}${line}`).join('\n');

  return {
    value: `${value.slice(0, lineStart)}${prefixed}${value.slice(lineEnd)}`,
    selectionStart: lineStart,
    selectionEnd: lineStart + prefixed.length,
  };
}

/**
 * Keyboard shortcuts of the editor (with Ctrl, or Cmd on macOS).
 */
export const MARKDOWN_SHORTCUTS: Record<string, MarkdownFormat> = {
  b: 'bold',
  i: 'italic',
  k: 'link',
  e: 'code',
};
