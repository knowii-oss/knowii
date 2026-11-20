import { describe, expect, it } from 'vitest';

// The fill tokens `danger` and `secondary` are for backgrounds with white text on them. As a text color on a surface
// they are far below the contrast that WCAG 1.4.3 asks for (about 1.9:1 in the dark theme, #1570). Text uses
// `text-danger-text` and `text-secondary-text`.
const sources = import.meta.glob<string>(['./**/*.tsx', '!./**/*.spec.tsx', '!./**/*.stories.tsx'], {
  query: '?raw',
  import: 'default',
  eager: true,
});

const FILL_TOKEN_AS_TEXT = /(?<![\w-])text-(danger|secondary)(?![\w-])/;

describe('text colors', () => {
  it('finds the component files', () => {
    expect(Object.keys(sources)).toContain('./Components/ui/InputError.tsx');
  });

  it('never uses a fill token as a text color', () => {
    const files = Object.entries(sources)
      .filter(([, source]) => FILL_TOKEN_AS_TEXT.test(source))
      .map(([path]) => path);
    expect(files).toEqual([]);
  });
});
