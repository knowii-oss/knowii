import { describe, expect, it } from 'vitest';
import { applyMarkdownFormat } from './markdown-format';

describe('applyMarkdownFormat', () => {
  it('wraps the selection and keeps it selected', () => {
    expect(applyMarkdownFormat({ value: 'say hello now', selectionStart: 4, selectionEnd: 9 }, 'bold')).toEqual({
      value: 'say **hello** now',
      selectionStart: 6,
      selectionEnd: 11,
    });
  });

  it('inserts a selected placeholder when nothing is selected', () => {
    const result = applyMarkdownFormat({ value: 'ab', selectionStart: 1, selectionEnd: 1 }, 'italic');
    expect(result.value).toBe('a_italic text_b');
    expect(result.value.slice(result.selectionStart, result.selectionEnd)).toBe('italic text');
  });

  it('turns the selection into a link and selects the url placeholder', () => {
    const result = applyMarkdownFormat({ value: 'see docs', selectionStart: 4, selectionEnd: 8 }, 'link');
    expect(result.value).toBe('see [docs](url)');
    expect(result.value.slice(result.selectionStart, result.selectionEnd)).toBe('url');
  });

  it('prefixes every selected line for lists and quotes', () => {
    const value = 'intro\none\ntwo\nend';
    const start = value.indexOf('one');
    const end = value.indexOf('two') + 2;
    expect(applyMarkdownFormat({ value, selectionStart: start, selectionEnd: end }, 'numberedList').value).toBe(
      'intro\n1. one\n2. two\nend',
    );
    expect(applyMarkdownFormat({ value, selectionStart: start + 1, selectionEnd: start + 1 }, 'quote').value).toBe(
      'intro\n> one\ntwo\nend',
    );
    expect(applyMarkdownFormat({ value: 'a', selectionStart: 0, selectionEnd: 1 }, 'bulletList').value).toBe('- a');
  });
});
