import { useId } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { FaPen } from 'react-icons/fa6';
import type { TextareaProps } from './Textarea';
import { Textarea } from './Textarea';

const ERROR_TEXT = 'Enter a description of 10 characters or more.';
const HINT_TEXT = 'The members see it on the page of the space.';

interface StoryArgs extends TextareaProps {
  label?: string;
  /**
   * A hint under the label, linked to the field with `aria-describedby`
   */
  withHint?: boolean;
}

/**
 * The field with its label, as a form shows it. The hint and, with `invalid`, the error text are linked with
 * `aria-describedby`.
 */
function LabelledTextarea({ label = 'Description', withHint = false, ...args }: StoryArgs) {
  const id = useId();
  const hintId = `${id}-hint`;
  const errorId = `${id}-error`;
  const describedBy = [withHint ? hintId : null, args.invalid ? errorId : null].filter(Boolean).join(' ');
  return (
    <div className="flex max-w-md flex-col gap-1">
      <label htmlFor={id} className="text-sm font-bold">
        {label}
      </label>
      {withHint && (
        <p id={hintId} className="text-sm text-muted">
          {HINT_TEXT}
        </p>
      )}
      <Textarea {...args} id={id} {...(describedBy ? { 'aria-describedby': describedBy } : {})} />
      {args.invalid && (
        <p id={errorId} className="text-sm text-danger-text">
          {ERROR_TEXT}
        </p>
      )}
    </div>
  );
}

/**
 * A text field of several lines, for a description, a bio or a short message. For the text of a post, use the
 * `MarkdownEditor`. Give it a label (`<label htmlFor>`). Use `invalid` for a value that the form refuses, and show the
 * reason under the field. The member can make it taller.
 *
 * Accessibility: a native `<textarea>`; the name comes from the label; `invalid` sets `aria-invalid`. The icon at the
 * start is decorative.
 */
const meta = {
  component: Textarea,
  render: (args) => <LabelledTextarea {...args} />,
  args: {
    placeholder: 'What the members of the space talk about',
    onChange: fn(),
  },
} satisfies Meta<StoryArgs>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * Enter makes a new line: it does not submit the form
 */
export const Default: Story = {
  play: async ({ args, canvasElement }) => {
    const field = within(canvasElement).getByRole('textbox', { name: 'Description' });
    await userEvent.tab();
    await expect(field).toHaveFocus();
    await userEvent.keyboard('Seeds{Enter}Compost');
    await expect(field).toHaveValue('Seeds\nCompost');
    await expect(args.onChange).toHaveBeenCalledTimes(13);
  },
};

export const WithValue: Story = {
  args: { defaultValue: 'Tips about the seeds of old tomatoes, beans and peppers.' },
};

export const WithIcon: Story = {
  args: { icon: <FaPen /> },
  play: async ({ canvasElement }) => {
    await expect(canvasElement.querySelector('[aria-hidden="true"] svg')).not.toBeNull();
  },
};

export const WithHint: Story = {
  args: { withHint: true },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('textbox', { name: 'Description' })).toHaveAccessibleDescription(HINT_TEXT);
  },
};

export const WithError: Story = {
  args: { invalid: true, withHint: true, defaultValue: 'Seeds' },
  play: async ({ canvasElement }) => {
    const field = within(canvasElement).getByRole('textbox', { name: 'Description' });
    await expect(field).toHaveAttribute('aria-invalid', 'true');
    await expect(field).toHaveAccessibleDescription(`${HINT_TEXT} ${ERROR_TEXT}`);
  },
};

export const Disabled: Story = {
  args: { disabled: true, defaultValue: 'Tips about the seeds of old tomatoes, beans and peppers.' },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('textbox', { name: 'Description' })).toBeDisabled();
  },
};

export const ReadOnly: Story = {
  args: { readOnly: true, defaultValue: 'Tips about the seeds of old tomatoes, beans and peppers.' },
  play: async ({ canvasElement }) => {
    const field = within(canvasElement).getByRole('textbox', { name: 'Description' });
    await userEvent.type(field, ' More');
    await expect(field).toHaveValue('Tips about the seeds of old tomatoes, beans and peppers.');
  },
};

export const MoreRows: Story = {
  args: { rows: 8 },
};

export const LongText: Story = {
  args: {
    defaultValue: Array.from(
      { length: 6 },
      () =>
        'The members share the seeds of old tomatoes, beans and peppers, and tell when they sowed them, where they planted them and what they picked.',
    ).join('\n\n'),
  },
};
