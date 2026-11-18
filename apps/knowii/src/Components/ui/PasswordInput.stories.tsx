import { useId, useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import type { PasswordInputProps } from './PasswordInput';
import { PasswordInput } from './PasswordInput';

const ERROR_TEXT = 'Enter a password of 8 characters or more.';

/**
 * The field with its label and its value, as a form shows it. With `invalid`, the error text is linked with
 * `aria-describedby`. The strength hint needs the value: the field is controlled.
 */
function LabelledPasswordInput({ label = 'Password', value: valueAtStart, onChange, ...args }: PasswordInputProps & { label?: string }) {
  const id = useId();
  const errorId = `${id}-error`;
  const [value, setValue] = useState(valueAtStart ?? '');
  return (
    <div className="flex max-w-md flex-col gap-1">
      <label htmlFor={id} className="text-sm font-bold">
        {label}
      </label>
      <PasswordInput
        {...args}
        id={id}
        value={value}
        onChange={(event) => {
          setValue(event.target.value);
          onChange?.(event);
        }}
        {...(args.invalid ? { 'aria-describedby': errorId } : {})}
      />
      {args.invalid && (
        <p id={errorId} className="text-sm text-danger-text">
          {ERROR_TEXT}
        </p>
      )}
    </div>
  );
}

/**
 * A field for a password, with a button that shows and hides it. `showStrength` shows a rough strength hint under the
 * field while the member types (the server checks the real rules). Give it a label (`<label htmlFor>`) and the
 * `autoComplete` of the form ("current-password" or "new-password").
 *
 * Accessibility: the name comes from the label; `invalid` sets `aria-invalid`. The button is a toggle button
 * (`aria-pressed`) named "Show password" or "Hide password", and controls the field (`aria-controls`). The strength hint
 * is a polite live region, linked to the field with `aria-describedby`, after the description of the form.
 */
const meta = {
  component: PasswordInput,
  render: (args) => <LabelledPasswordInput {...args} />,
  args: {
    autoComplete: 'current-password',
    onChange: fn(),
  },
} satisfies Meta<typeof PasswordInput>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The keyboard path: type the password, Tab to the button, Enter shows the password, Enter again hides it
 */
export const Default: Story = {
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    const input = canvas.getByLabelText('Password');
    await expect(input).toHaveAttribute('type', 'password');
    await userEvent.tab();
    await expect(input).toHaveFocus();
    await userEvent.keyboard('Basil2026');
    await expect(input).toHaveValue('Basil2026');
    await expect(args.onChange).toHaveBeenCalledTimes(9);

    await userEvent.tab();
    const show = canvas.getByRole('button', { name: 'Show password' });
    await expect(show).toHaveFocus();
    await expect(show).toHaveAttribute('aria-pressed', 'false');
    await expect(show).toHaveAttribute('aria-controls', input.id);
    await userEvent.keyboard('{Enter}');
    await expect(input).toHaveAttribute('type', 'text');
    const hide = canvas.getByRole('button', { name: 'Hide password' });
    await expect(hide).toHaveFocus();
    await expect(hide).toHaveAttribute('aria-pressed', 'true');
    await userEvent.keyboard('{Enter}');
    await expect(input).toHaveAttribute('type', 'password');
  },
};

export const Visible: Story = {
  args: { value: 'Basil2026' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(canvas.getByRole('button', { name: 'Show password' }));
    await expect(canvas.getByRole('textbox', { name: 'Password' })).toHaveValue('Basil2026');
  },
};

/**
 * The strength hint shows once the member types, and changes with the password
 */
export const WithStrength: Story = {
  args: { showStrength: true, autoComplete: 'new-password' },
  render: (args) => <LabelledPasswordInput {...args} label="New password" />,
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const input = canvas.getByLabelText('New password');
    await expect(input).not.toHaveAttribute('aria-describedby');
    await expect(canvas.queryByText('Too simple')).toBeNull();

    await userEvent.type(input, 'basil');
    await expect(input).toHaveAccessibleDescription('Too simple');
    await expect(canvas.getByText('Too simple').closest('[aria-live]')).toHaveAttribute('aria-live', 'polite');
    await userEvent.type(input, '26');
    await expect(input).toHaveAccessibleDescription('Average complexity');
    await userEvent.type(input, 'Garden');
    await expect(input).toHaveAccessibleDescription('Complex password');
  },
};

export const WeakPassword: Story = {
  args: { showStrength: true, value: 'basil', autoComplete: 'new-password' },
};

export const MediumPassword: Story = {
  args: { showStrength: true, value: 'basil26', autoComplete: 'new-password' },
};

export const StrongPassword: Story = {
  args: { showStrength: true, value: 'Basil26Garden', autoComplete: 'new-password' },
};

export const CustomStrengthLabels: Story = {
  args: {
    showStrength: true,
    value: 'Basil26Garden',
    autoComplete: 'new-password',
    strengthLabels: { weak: 'Weak', medium: 'Fair', strong: 'Strong' },
  },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByLabelText('Password')).toHaveAccessibleDescription('Strong');
  },
};

/**
 * The error of the form and the strength hint: the field is described by both
 */
export const WithError: Story = {
  args: { invalid: true, showStrength: true, value: 'basil', autoComplete: 'new-password' },
  play: async ({ canvasElement }) => {
    const input = within(canvasElement).getByLabelText('Password');
    await expect(input).toHaveAttribute('aria-invalid', 'true');
    await expect(input).toHaveAccessibleDescription(`${ERROR_TEXT} Too simple`);
  },
};

export const Disabled: Story = {
  args: { disabled: true, value: 'Basil2026' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByLabelText('Password')).toBeDisabled();
    await expect(canvas.getByRole('button', { name: 'Show password' })).toBeDisabled();
  },
};

export const Small: Story = {
  args: { size: 'sm' },
};

export const Large: Story = {
  args: { size: 'lg' },
};

export const LongText: Story = {
  args: {
    showStrength: true,
    autoComplete: 'new-password',
    value: 'Tomatoes, peppers and beans grow well in the sun of the south wall of the old garden 2026',
  },
};
