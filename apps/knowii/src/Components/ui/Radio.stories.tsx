import { useId } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import type { RadioProps } from './Radio';
import { Radio } from './Radio';

const LEGEND = 'Size of the garden';
const DESCRIPTION = 'We use it to suggest plants that fit.';

interface Choice {
  value: string;
  label: string;
}

const choices: readonly Choice[] = [
  { value: 'balcony', label: 'A balcony' },
  { value: 'small', label: 'A small garden' },
  { value: 'large', label: 'A large garden' },
];

interface StoryArgs extends RadioProps {
  /**
   * The choices of the group, one radio each
   */
  choices?: readonly Choice[];
  /**
   * The value that is checked at the start
   */
  checkedAtStart?: string;
  /**
   * The value of the choice that is disabled
   */
  disabledChoice?: string;
  /**
   * A text under the legend, linked to the group with `aria-describedby`
   */
  describeGroup?: boolean;
}

/**
 * A group of radios in a fieldset, as a form shows it: each radio sits in its label, and the legend names the group
 */
function RadioGroup({ choices: groupChoices = choices, checkedAtStart, disabledChoice, describeGroup = false, ...args }: StoryArgs) {
  const name = useId();
  const descriptionId = `${name}-description`;
  return (
    <fieldset className="flex max-w-md flex-col gap-3" {...(describeGroup ? { 'aria-describedby': descriptionId } : {})}>
      <legend className="text-sm font-bold">{LEGEND}</legend>
      {describeGroup && (
        <p id={descriptionId} className="-mt-2 text-sm text-muted">
          {DESCRIPTION}
        </p>
      )}
      {groupChoices.map((choice) => (
        <label key={choice.value} className="flex cursor-pointer items-start gap-3">
          <Radio
            {...args}
            name={name}
            value={choice.value}
            defaultChecked={choice.value === checkedAtStart}
            disabled={args.disabled === true || choice.value === disabledChoice}
          />
          <span>{choice.label}</span>
        </label>
      ))}
    </fieldset>
  );
}

/**
 * A native radio button with the Knowii look, for one choice among a few options that stay visible. Wrap each radio in
 * a `<label>`, give the radios of a group the same `name`, and put them in a `<fieldset>` with a `<legend>`. For two or
 * three short options side by side, use a `SegmentedControl`; for a long list, a `Select`. It is 24 px: the minimum
 * target size (WCAG 2.5.8).
 *
 * Accessibility: the name comes from the label, and the legend names the group. Tab moves into the group (to the checked
 * radio), the arrow keys change the choice, and Space checks the radio that has the focus.
 */
const meta = {
  component: Radio,
  render: (args) => <RadioGroup {...args} />,
  args: {
    onChange: fn(),
  },
} satisfies Meta<StoryArgs>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The keyboard path: Tab moves into the group, Space checks the first radio, the arrow keys move the choice
 */
export const Default: Story = {
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    const group = canvas.getByRole('group', { name: LEGEND });
    const balcony = within(group).getByRole('radio', { name: 'A balcony' });
    const small = within(group).getByRole('radio', { name: 'A small garden' });
    await userEvent.tab();
    await expect(balcony).toHaveFocus();
    await userEvent.keyboard(' ');
    await expect(balcony).toBeChecked();
    await userEvent.keyboard('{ArrowDown}');
    await expect(small).toHaveFocus();
    await expect(small).toBeChecked();
    await expect(balcony).not.toBeChecked();
    await expect(args.onChange).toHaveBeenCalledTimes(2);
  },
};

/**
 * Tab goes to the checked radio of the group
 */
export const Checked: Story = {
  args: { checkedAtStart: 'small' },
  play: async ({ canvasElement }) => {
    const small = within(canvasElement).getByRole('radio', { name: 'A small garden' });
    await expect(small).toBeChecked();
    await userEvent.tab();
    await expect(small).toHaveFocus();
  },
};

export const WithDescription: Story = {
  args: { describeGroup: true },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('group', { name: LEGEND })).toHaveAccessibleDescription(DESCRIPTION);
  },
};

/**
 * One choice is disabled: the arrow keys skip it
 */
export const OneChoiceDisabled: Story = {
  args: { checkedAtStart: 'balcony', disabledChoice: 'small' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('radio', { name: 'A small garden' })).toBeDisabled();
    await userEvent.tab();
    await userEvent.keyboard('{ArrowDown}');
    await expect(canvas.getByRole('radio', { name: 'A large garden' })).toBeChecked();
  },
};

export const Disabled: Story = {
  args: { disabled: true },
  play: async ({ canvasElement }) => {
    for (const radio of within(canvasElement).getAllByRole('radio')) {
      await expect(radio).toBeDisabled();
    }
  },
};

export const DisabledChecked: Story = {
  args: { disabled: true, checkedAtStart: 'small' },
};

export const LongText: Story = {
  args: {
    choices: [
      { value: 'balcony', label: 'A balcony or a terrace, with pots and boxes, in the sun for part of the day' },
      { value: 'small', label: 'A small garden behind the house, with a few beds of vegetables and a compost bin' },
      { value: 'large', label: 'A large garden or a field, with an orchard, a greenhouse and room for the hens' },
    ],
  },
};
