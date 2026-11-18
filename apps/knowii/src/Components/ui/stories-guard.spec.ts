import { describe, expect, it } from 'vitest';
import type { StoryObj } from '@storybook/react-vite';
import { composeStories } from '@storybook/react-vite';

// The guard of the toolkit (#657): every component of Components/ui has a story file, and every story file holds
// stories. The stories are the test cases (stories.spec.tsx).

// A story file: the meta (default export) and the stories (named exports)
type StoryFile = Parameters<typeof composeStories>[0] & Record<string, StoryObj>;

const sourceFiles = import.meta.glob<Record<string, unknown>>(['./**/*.tsx', '!./**/*.spec.tsx', '!./**/*.stories.tsx'], {
  eager: true,
});
const storyFiles = import.meta.glob<StoryFile>('./**/*.stories.tsx', { eager: true });

/**
 * A React component: a function (or a class) with a name in PascalCase, or the object that memo() and forwardRef()
 * return
 */
function isComponent(name: string, value: unknown): boolean {
  if (!/^[A-Z][A-Za-z0-9]*$/.test(name)) {
    return false;
  }
  return typeof value === 'function' || (typeof value === 'object' && value !== null && '$$typeof' in value);
}

/**
 * "./Button.tsx" and "./Button.stories.tsx" give "Button"
 */
function componentName(path: string): string {
  return path.replace(/^\.\//, '').replace(/(\.stories)?\.tsx$/, '');
}

const componentFiles = Object.entries(sourceFiles)
  .filter(([, exports]) => Object.entries(exports).some(([name, value]) => isComponent(name, value)))
  .map(([path]) => componentName(path))
  .sort();
const filesWithStories = new Set(Object.keys(storyFiles).map(componentName));

describe('the stories of Components/ui', () => {
  it('finds the component files and the story files', () => {
    expect(componentFiles).toContain('Button');
    expect(filesWithStories).toContain('Button');
  });

  it('has a story file for every component file', () => {
    expect(componentFiles.filter((name) => !filesWithStories.has(name))).toEqual([]);
  });

  it.each(Object.entries(storyFiles))('%s exports one story or more', (_path, storyFile) => {
    expect(Object.keys(composeStories(storyFile)).length).toBeGreaterThan(0);
  });
});
