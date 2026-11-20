import { describe, expect, it } from 'vitest';

// The catalogue is public: the Storybook of the toolkit is on the page site of the repository (#657, ADR 0049).
// This spec reads the story files and the documentation pages as text. Only the Storybook build compiles a page
// (docs/<Name>.mdx): the lint, the type check and the other specs do not read it.

const storyFiles = import.meta.glob<string>('./**/*.stories.tsx', { query: '?raw', import: 'default', eager: true });
const pages = import.meta.glob<string>('./**/*.mdx', { query: '?raw', import: 'default', eager: true });

// The domains that are reserved for examples (RFC 2606): an address there belongs to nobody
const EXAMPLE_DOMAINS = new Set(['example.com', 'example.org', 'example.net']);

const EMAIL_ADDRESS = /[A-Za-z0-9._%+}-]+@([A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,})/g;

/**
 * The email addresses of a text whose domain is not reserved for examples
 */
function realEmailAddresses(text: string): string[] {
  return [...text.matchAll(EMAIL_ADDRESS)].filter((match) => !EXAMPLE_DOMAINS.has((match[1] ?? '').toLowerCase())).map((match) => match[0]);
}

/**
 * The title of a page: the `title` of its `<Meta>` block
 */
function pageTitle(text: string): string | undefined {
  return /<Meta\s+title="([^"]+)"\s*\/>/.exec(text)?.[1];
}

describe('the catalogue of Components/ui', () => {
  it('finds the story files and the pages', () => {
    expect(Object.keys(storyFiles)).toContain('./Button.stories.tsx');
    expect(Object.keys(pages)).toContain('./docs/Introduction.mdx');
  });

  it('tells an example address from another one', () => {
    // An address whose first part ends a template expression is found too
    expect(realEmailAddresses('`${name}@' + 'mail.example.io`')).toHaveLength(1);
    expect(realEmailAddresses('Write to rose@example.com, to Rose@Example.ORG or to rose@example.net.')).toEqual([]);
    expect(realEmailAddresses('Write to rose@mail.example.com or to rose@garden.test.')).toEqual([
      'rose@mail.example.com',
      'rose@garden.test',
    ]);
    expect(realEmailAddresses("import { Meta } from '@storybook/addon-docs/blocks';")).toEqual([]);
  });

  // The order of the list (preview.tsx) knows two groups of pages: the introduction, then the rules
  it.each(Object.entries(pages))('%s has a title: "Introduction", or a name in the group "Rules"', (_path, text) => {
    const title = pageTitle(text);
    expect(title).toBeDefined();
    expect(title === 'Introduction' || title?.startsWith('Rules/')).toBe(true);
  });

  it.each(Object.entries({ ...storyFiles, ...pages }))('%s holds only example email addresses', (_path, text) => {
    expect(realEmailAddresses(text)).toEqual([]);
  });
});
