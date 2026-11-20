import { useId } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import type { InputErrorProps } from './InputError';
import { InputError } from './InputError';
import { InputLabel } from './InputLabel';
import { TextInput } from './TextInput';

const MESSAGE = 'Enter a name of 3 characters or more.';
const LONG_MESSAGE =
  'This name is already the name of another community of this instance, and two communities cannot have the same ' +
  'name. Choose another name of 3 to 255 characters, or add the name of your town or of your group to this one.';

/**
 * The error under its field, as a form shows it: the field is `invalid` and names the id of the message in
 * `aria-describedby`.
 */
function FieldWithError(args: InputErrorProps) {
  const id = useId();
  const errorId = `${id}-error`;
  return (
    <div className="max-w-md">
      <InputLabel htmlFor={id}>Name</InputLabel>
      <TextInput id={id} className="mt-1" defaultValue="Kn" invalid aria-describedby={errorId} />
      <InputError {...args} id={errorId} className="mt-2" />
    </div>
  );
}

/**
 * The text of the error of one form field, under the field: what the form or the server refuses, and what to do. It
 * renders nothing without a message and without children, so a form always renders it and gives it the error of the
 * field, or nothing. `message` comes first; the children show only without it.
 *
 * Not the status of a save (`SaveStatus`), not a hint under a field, and not an error of the whole page.
 *
 * Accessibility: a paragraph with `role="alert"`, so a screen reader says the message when it shows. Give it an `id`,
 * name that id in `aria-describedby` of the field and set `invalid` on the field: the message is then the description
 * of the field, and a screen reader says it again when the focus comes back to the field. The text says what is
 * wrong: the color is not the only sign.
 */
const meta = {
  component: InputError,
  args: {
    message: MESSAGE,
  },
} satisfies Meta<typeof InputError>;

export default meta;
type Story = StoryObj<typeof meta>;

export const WithMessage: Story = {
  play: async ({ canvasElement }) => {
    const message = within(canvasElement).getByRole('alert');
    await expect(message).toHaveTextContent(MESSAGE);
    await expect(message.tagName).toBe('P');
  },
};

/**
 * The text as children, for a message that is more than a string
 */
/**
 * With both, `message` shows and the children do not
 */
export const MessageBeforeChildren: Story = {
  args: { message: MESSAGE, children: 'Another text' },
  play: async ({ canvasElement }) => {
    const message = within(canvasElement).getByRole('alert');
    await expect(message).toHaveTextContent(MESSAGE);
    await expect(message).not.toHaveTextContent('Another text');
  },
};

export const WithChildren: Story = {
  args: {
    message: undefined,
    children: (
      <>
        The name <strong>Kn</strong> is too short. Enter 3 characters or more.
      </>
    ),
  },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('alert')).toHaveTextContent('The name Kn is too short. Enter 3 characters or more.');
  },
};

/**
 * Without a message and without children, nothing renders: no empty alert, and no space under the field
 */
export const WithoutMessage: Story = {
  args: { message: undefined, className: 'mt-2' },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).queryByRole('alert')).toBeNull();
    await expect(canvasElement.querySelector('p, div')).toBeNull();
    await expect(canvasElement.textContent).toBe('');
  },
};

/**
 * The field names the id of the message in `aria-describedby`: the message is the description of the field
 */
export const DescribesItsField: Story = {
  render: (args) => <FieldWithError {...args} />,
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const field = canvas.getByRole('textbox', { name: 'Name' });
    await expect(field).toHaveAttribute('aria-invalid', 'true');
    await expect(field).toHaveAccessibleDescription(MESSAGE);
    await expect(canvas.getByRole('alert')).toHaveAttribute('id', field.getAttribute('aria-describedby'));
  },
};

/**
 * A long message wraps under the field on a phone: the page does not scroll sideways
 */
export const LongMessageOnPhone: Story = {
  args: { message: LONG_MESSAGE },
  render: (args) => <FieldWithError {...args} />,
  globals: { viewport: { value: 'phone', isRotated: false } },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('alert')).toHaveTextContent(LONG_MESSAGE);
  },
};
