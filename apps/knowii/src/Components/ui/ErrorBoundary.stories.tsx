import { useId, useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, waitFor, within } from 'storybook/test';
import type { ErrorBoundaryProps } from './ErrorBoundary';
import { ErrorBoundary } from './ErrorBoundary';

// The error that the stories throw on purpose
const EXPECTED_ERROR = 'The list of the seeds could not be read';

// The console of the machine, kept when the file loads: each story gives it back
const MACHINE_CONSOLE_ERROR = console.error;

function isExpectedError(value: unknown): boolean {
  return (value instanceof Error && value.message === EXPECTED_ERROR) || (typeof value === 'string' && value.includes(EXPECTED_ERROR));
}

/**
 * A part of a page that fails to render while `broken` is true
 */
function SeedList({ broken }: { broken: boolean }) {
  if (broken) {
    throw new Error(EXPECTED_ERROR);
  }
  return (
    <ul className="list-disc pl-5">
      <li>Tomato, Black Krim</li>
      <li>Bean, Blue Lake</li>
    </ul>
  );
}

interface StoryArgs extends Omit<ErrorBoundaryProps, 'children'> {
  /**
   * The child fails to render at the start
   */
  broken: boolean;
}

/**
 * The boundary around a part that fails, and a switch outside it that repairs the part, so that "Try again" can show it
 */
function BoundaryAroundAPart({ broken: brokenAtStart, ...args }: StoryArgs) {
  const [broken, setBroken] = useState(brokenAtStart);
  const id = useId();
  return (
    <div className="flex flex-col gap-4">
      <ErrorBoundary {...args}>
        <SeedList broken={broken} />
      </ErrorBoundary>
      <div className="flex items-center gap-2 text-sm text-muted">
        <input id={id} type="checkbox" checked={broken} onChange={(event) => setBroken(event.target.checked)} />
        <label htmlFor={id}>The list of the seeds fails to render</label>
      </div>
    </div>
  );
}

/**
 * Two pages of seeds, as the layout shows them: the address of the page is the `resetKey`. The page of the tomatoes
 * fails to render.
 */
function TwoPages({ broken: _broken, ...args }: StoryArgs) {
  const [page, setPage] = useState('/seeds/tomatoes');
  return (
    <div className="flex flex-col gap-4">
      <div className="flex gap-2">
        <button type="button" className="underline" onClick={() => setPage('/seeds/tomatoes')}>
          Tomatoes
        </button>
        <button type="button" className="underline" onClick={() => setPage('/seeds/beans')}>
          Beans
        </button>
      </div>
      <ErrorBoundary {...args} resetKey={page}>
        <SeedList broken={page === '/seeds/tomatoes'} />
      </ErrorBoundary>
    </div>
  );
}

/**
 * Catches an error while its children render, and shows a message instead of an empty page. The `page` variant (the
 * default) goes around the content of a page; `resetKey` (the address of the page) clears the error on a visit. The
 * `block` variant goes around one piece of member content (a post, a comment): one line, and the rest of the list
 * stays. The error goes to the console and to `onError`.
 *
 * Accessibility: the message has `role="alert"`, so a screen reader reads it at once. "Try again" renders the children
 * again; "Reload the page" reloads it. The icons are hidden from screen readers.
 */
// No `component`: the boundary is a class, and a class takes no props other than its own (the stories add `broken`)
const meta = {
  render: (args) => <BoundaryAroundAPart {...args} />,
  args: {
    broken: true,
    onError: fn(),
  },
  // React and the boundary write the error that the stories throw to the console: the story hides that error, and only
  // that one, and gives the console back after the story
  beforeEach: () => {
    console.error = (...values: unknown[]) => {
      if (!values.some(isExpectedError)) {
        MACHINE_CONSOLE_ERROR(...values);
      }
    };
    return () => {
      console.error = MACHINE_CONSOLE_ERROR;
    };
  },
} satisfies Meta<StoryArgs>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * Nothing fails: the boundary shows its children and nothing else
 */
export const WithoutError: Story = {
  args: { broken: false },
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByText('Tomato, Black Krim')).toBeInTheDocument();
    await expect(canvas.queryByRole('alert')).toBeNull();
    await expect(args.onError).not.toHaveBeenCalled();
  },
};

/**
 * A child fails: the message of the page, with "Try again" and "Reload the page", and the error goes to `onError`
 */
export const PageMessage: Story = {
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('alert')).toHaveTextContent('Something went wrong on this page');
    await expect(canvas.getByRole('button', { name: 'Try again' })).toBeInTheDocument();
    await expect(canvas.getByRole('button', { name: 'Reload the page' })).toBeInTheDocument();
    await expect(args.onError).toHaveBeenCalledWith(expect.objectContaining({ message: EXPECTED_ERROR }), expect.anything());
  },
};

/**
 * The keyboard path: the part is repaired, and Enter on "Try again" renders it again. The button goes away with the
 * message, and the focus goes to the page (body), not to the content that is back.
 */
export const TryAgain: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const repair = canvas.getByRole('checkbox', { name: 'The list of the seeds fails to render' });
    await userEvent.click(repair);
    await expect(canvas.getByRole('alert')).toBeInTheDocument();

    await userEvent.tab({ shift: true });
    await userEvent.tab({ shift: true });
    await expect(canvas.getByRole('button', { name: 'Try again' })).toHaveFocus();
    await userEvent.keyboard('{Enter}');
    await waitFor(() => expect(canvas.getByText('Tomato, Black Krim')).toBeInTheDocument());
    await expect(canvas.queryByRole('alert')).toBeNull();
  },
};

/**
 * "Try again" while the part still fails: the message stays
 */
export const TryAgainStillFails: Story = {
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(canvas.getByRole('button', { name: 'Try again' }));
    await expect(canvas.getByRole('alert')).toHaveTextContent('Something went wrong on this page');
    await expect(args.onError).toHaveBeenCalledTimes(2);
  },
};

/**
 * A visit to another page (a new `resetKey`) clears the error, without "Try again"
 */
export const ClearedByAVisit: Story = {
  render: (args) => <TwoPages {...args} />,
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('alert')).toBeInTheDocument();
    await userEvent.click(canvas.getByRole('button', { name: 'Beans' }));
    await waitFor(() => expect(canvas.getByText('Bean, Blue Lake')).toBeInTheDocument());
    await expect(canvas.queryByRole('alert')).toBeNull();
  },
};

/**
 * One line for a piece of member content: the next post stays on the page
 */
export const BlockMessage: Story = {
  args: { variant: 'block', label: 'this post' },
  render: (args) => (
    <div className="flex flex-col gap-3">
      <BoundaryAroundAPart {...args} />
      <p>The next post: sow the beans when the soil is warm.</p>
    </div>
  ),
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('alert')).toHaveTextContent('We could not show this post.');
    await expect(canvas.queryByRole('button', { name: 'Reload the page' })).toBeNull();
    await expect(canvas.getByText(/The next post/)).toBeInTheDocument();
  },
};

/**
 * The block without a label says "this content"
 */
export const BlockMessageWithoutLabel: Story = {
  args: { variant: 'block' },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('alert')).toHaveTextContent('We could not show this content.');
  },
};

export const PageMessageOnPhone: Story = {
  globals: { viewport: { value: 'phone', isRotated: false } },
};
