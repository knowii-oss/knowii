import type { Result, RuleObject } from 'axe-core';
import axe from 'axe-core';
import { beforeAll, describe, expect, it } from 'vitest';
import type { StoryObj } from '@storybook/react-vite';
import { composeStories, setProjectAnnotations } from '@storybook/react-vite';
import preview from '../../../.storybook/preview';

// The stories are the test cases (#657): each story of Components/ui renders, runs its play function, and passes the
// automated accessibility check. Run them in a browser with `npm run storybook`.

// A story file: the meta (default export) and the stories (named exports)
type StoryFile = Parameters<typeof composeStories>[0] & Record<string, StoryObj>;

const storyFiles = import.meta.glob<StoryFile>('./**/*.stories.tsx', { eager: true });

const project = setProjectAnnotations([
  preview,
  {
    // A story shows a part of a page: in the test, the part sits in the main landmark of the page, so that the rule
    // "all content is in a landmark" (region) checks what it can check
    decorators: [
      (Story) => (
        <main>
          <Story />
        </main>
      ),
    ],
  },
]);

// The rules that cannot work in jsdom. They stay on in the browser (the accessibility panel of Storybook).
const RULES_OFF_IN_JSDOM: RuleObject = {
  // jsdom computes no colors and no layout: the contrast of text cannot be measured
  'color-contrast': { enabled: false },
};

function describeViolation(violation: Result): string {
  return `${violation.id}: ${violation.help} (${violation.nodes.map((node) => node.target.join(' ')).join(', ')})`;
}

// Sets up the testing library of the play functions for React (act around the events)
beforeAll(project.beforeAll);

describe.each(Object.entries(storyFiles))('%s', (_path, storyFile) => {
  it.each(Object.entries(composeStories(storyFile)))('%s', async (_name, Story) => {
    // Renders the story and runs its play function
    await Story.run();

    // The whole body: a dialog renders in a portal, outside the canvas of the story
    const results = await axe.run(document.body, { rules: RULES_OFF_IN_JSDOM });
    expect(results.violations.map(describeViolation)).toEqual([]);
  });
});
