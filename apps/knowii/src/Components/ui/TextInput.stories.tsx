import { useId } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { FaMagnifyingGlass, FaXmark } from 'react-icons/fa6';
import type { TextInputProps } from './TextInput';
import { TextInput } from './TextInput';

const ERROR_TEXT = 'Enter a name of 3 characters or more.';

/**
 * The field with its label, as a form shows it. With `invalid`, the error text is linked with `aria-describedby`.
 */
function LabelledTextInput({ label = 'Name', ...args }: TextInputProps & { label?: string }) {
  const id = useId();
  const errorId = `${id}-error`;
  return (
    <div className="flex max-w-md flex-col gap-1">
      <label htmlFor={id} className="text-sm font-bold">
        {label}
      </label>
      <TextInput {...args} id={id} {...(args.invalid ? { 'aria-describedby': errorId } : {})} />
      {args.invalid && (
        <p id={errorId} className="text-sm text-danger-text">
          {ERROR_TEXT}
        </p>
      )}
    </div>
  );
}

/**
 * A text field of one line. Give it a label (`<label htmlFor>`). Use `invalid` for a value that the form refuses, and
 * show the reason under the field.
 *
 * Accessibility: the name comes from the label; `invalid` sets `aria-invalid`. The icon at the start is decorative.
 */
const meta = {
  component: TextInput,
  render: (args) => <LabelledTextInput {...args} />,
  args: {
    placeholder: 'Knowii Community',
  },
} satisfies Meta<typeof TextInput>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  play: async ({ canvasElement }) => {
    const input = within(canvasElement).getByRole('textbox', { name: 'Name' });
    await userEvent.type(input, 'Readers');
    await expect(input).toHaveValue('Readers');
  },
};

export const Small: Story = {
  args: { size: 'sm' },
};

export const Large: Story = {
  args: { size: 'lg' },
};

export const WithIcon: Story = {
  args: { icon: <FaMagnifyingGlass />, type: 'search', placeholder: 'Search' },
};

export const WithButtonAtTheEnd: Story = {
  args: {
    defaultValue: 'Readers',
    trailing: (
      <button
        type="button"
        aria-label="Clear the name"
        className="flex size-9 cursor-pointer items-center justify-center rounded-md text-muted"
      >
        <FaXmark aria-hidden="true" />
      </button>
    ),
  },
};

export const WithError: Story = {
  args: { invalid: true, defaultValue: 'Kn' },
  play: async ({ canvasElement }) => {
    const input = within(canvasElement).getByRole('textbox', { name: 'Name' });
    await expect(input).toHaveAttribute('aria-invalid', 'true');
    await expect(input).toHaveAccessibleDescription(ERROR_TEXT);
  },
};

export const Disabled: Story = {
  args: { disabled: true, defaultValue: 'Readers' },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('textbox', { name: 'Name' })).toBeDisabled();
  },
};

/**
 * `allowedCharacters` blocks the characters that do not match, also in pasted text.
 */
export const AllowedCharacters: Story = {
  args: { allowedCharacters: /^[0-9+ ]+$/, type: 'tel', placeholder: '+32 470 12 34 56' },
  render: (args) => <LabelledTextInput label="Phone number" {...args} />,
  play: async ({ canvasElement }) => {
    const input = within(canvasElement).getByRole('textbox', { name: 'Phone number' });
    await userEvent.type(input, '+32 4a7b0');
    await expect(input).toHaveValue('+32 470');
  },
};

export const LongText: Story = {
  args: {
    defaultValue: 'The community of the readers who write one long note every week and share it with the other members',
  },
};
