import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import type { CheckboxProps } from './Checkbox';
import { Checkbox } from './Checkbox';

/**
 * The checkbox inside its label, as a form shows it: a click on the text toggles it too.
 */
function LabelledCheckbox({ label = 'Remember me', ...args }: CheckboxProps & { label?: string }) {
  return (
    <label className="flex max-w-md cursor-pointer items-start gap-3">
      <Checkbox {...args} />
      <span>{label}</span>
    </label>
  );
}

/**
 * A native checkbox with the Knowii look, for one choice that is on or off. Put it in a `<label>`, or give it an `id`
 * and a `<label htmlFor>`. It is 24 px: the minimum target size (WCAG 2.5.8).
 *
 * Accessibility: the name comes from the label. Space toggles it.
 */
const meta = {
  component: Checkbox,
  render: (args) => <LabelledCheckbox {...args} />,
  args: {
    onChange: fn(),
  },
} satisfies Meta<typeof Checkbox>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Unchecked: Story = {
  play: async ({ args, canvasElement }) => {
    const checkbox = within(canvasElement).getByRole('checkbox', { name: 'Remember me' });
    await userEvent.tab();
    await expect(checkbox).toHaveFocus();
    await userEvent.keyboard(' ');
    await expect(checkbox).toBeChecked();
    await expect(args.onChange).toHaveBeenCalledOnce();
  },
};

export const Checked: Story = {
  args: { defaultChecked: true },
};

export const Disabled: Story = {
  args: { disabled: true },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('checkbox', { name: 'Remember me' })).toBeDisabled();
  },
};

export const DisabledChecked: Story = {
  args: { disabled: true, defaultChecked: true },
};

export const LongText: Story = {
  render: (args) => (
    <LabelledCheckbox
      {...args}
      label="Send me an email when a member replies to one of my posts, mentions me, or adds me to a new space of the community"
    />
  ),
};
