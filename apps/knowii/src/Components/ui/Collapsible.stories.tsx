import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { Collapsible } from './Collapsible';
import { TextInput } from './TextInput';

const CONTENT = 'Sow the seeds in March, inside, and plant the seedlings out after the last frost.';

/**
 * A section that the member opens and closes, for content that most members do not need all the time (optional
 * fields of a form, the details of a setting). Closed by default; `defaultOpen` opens it at the start. Do not hide
 * content that every member needs, or an error of a form, in it.
 *
 * Accessibility: the native `<details>` and `<summary>` elements. The summary is a button for screen readers, with its
 * expanded state; Enter and Space open and close it. The chevron is hidden from screen readers.
 */
const meta = {
  component: Collapsible,
  render: (args) => (
    <div className="max-w-xl">
      <Collapsible {...args} />
    </div>
  ),
  args: {
    title: 'When to plant tomatoes',
    children: <p>{CONTENT}</p>,
  },
} satisfies Meta<typeof Collapsible>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * Tab gives the focus to the summary, and a click on it opens the section and closes it again. The browser opens it with
 * Enter and Space too; the test runner (jsdom) does not, so the play function clicks.
 */
export const Closed: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const details = canvasElement.querySelector('details');
    const summary = canvas.getByText('When to plant tomatoes');
    await expect(details).not.toHaveAttribute('open');
    await expect(canvas.getByText(CONTENT)).not.toBeVisible();

    await userEvent.tab();
    await expect(summary).toHaveFocus();
    await userEvent.click(summary);
    await expect(details).toHaveAttribute('open');
    await expect(canvas.getByText(CONTENT)).toBeVisible();
    await expect(summary).toHaveFocus();
    await userEvent.click(summary);
    await expect(details).not.toHaveAttribute('open');
  },
};

export const Open: Story = {
  args: { defaultOpen: true },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvasElement.querySelector('details')).toHaveAttribute('open');
    await expect(canvas.getByText(CONTENT)).toBeVisible();
    await userEvent.click(canvas.getByText('When to plant tomatoes'));
    await expect(canvasElement.querySelector('details')).not.toHaveAttribute('open');
  },
};

/**
 * Optional fields of a form, as the profile form holds them
 */
export const WithFields: Story = {
  args: {
    title: 'Social links',
    defaultOpen: true,
    children: (
      <div className="flex flex-col gap-1">
        <label htmlFor="collapsible-website" className="text-sm font-bold">
          Website
        </label>
        <TextInput id="collapsible-website" type="url" placeholder="https://example.com" />
      </div>
    ),
  },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.tab();
    await userEvent.tab();
    await expect(canvas.getByRole('textbox', { name: 'Website' })).toHaveFocus();
  },
};

export const LongText: Story = {
  args: {
    title: 'When to sow, plant out, water and pick the tomatoes, the peppers and the beans of a small vegetable garden',
    defaultOpen: true,
    children: (
      <div className="flex flex-col gap-2">
        {Array.from({ length: 4 }, (_, index) => (
          <p key={index}>
            {CONTENT} Water the plants at the foot, in the morning, and never on the leaves. Pick the fruits when they are ripe, and leave
            the last ones on the plant to keep their seeds.
          </p>
        ))}
      </div>
    ),
  },
};
