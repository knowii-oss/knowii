import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { FaBook, FaComments, FaGraduationCap } from 'react-icons/fa6';
import type { SegmentedControlProps } from './SegmentedControl';
import { SegmentedControl } from './SegmentedControl';

type Kind = 'discussion' | 'course' | 'resources';

const LABEL = 'Kind of space';

const options = [
  { value: 'discussion', label: 'Discussion' },
  { value: 'course', label: 'Course' },
  { value: 'resources', label: 'Resources' },
] as const;

const optionsWithIcons = [
  { value: 'discussion', label: 'Discussion', icon: <FaComments /> },
  { value: 'course', label: 'Course', icon: <FaGraduationCap /> },
  { value: 'resources', label: 'Resources', icon: <FaBook /> },
] as const;

const descriptions: Record<Kind, string> = {
  discussion: 'Members write posts and reply to them.',
  course: 'Lessons in an order, with the progress of each member.',
  resources: 'Files and links that members share.',
};

interface StoryArgs extends SegmentedControlProps<Kind> {
  /**
   * Show the description of the selected choice under the control
   */
  describeChoice?: boolean;
}

/**
 * The control with its visible label, as a form shows it. The story keeps the selected value.
 */
function LabelledSegmentedControl({ value: valueAtStart, onChange, describeChoice = false, ...args }: StoryArgs) {
  const [value, setValue] = useState<Kind>(valueAtStart);
  return (
    <div className="flex max-w-md flex-col gap-1">
      <span id="segmented-control-label" className="text-sm font-bold">
        {LABEL}
      </span>
      <SegmentedControl<Kind>
        {...args}
        aria-labelledby="segmented-control-label"
        value={value}
        onChange={(next) => {
          setValue(next);
          onChange(next);
        }}
        {...(describeChoice ? { description: descriptions[value] } : {})}
      />
    </div>
  );
}

/**
 * One choice between a few options, side by side (a visibility, a kind, a duration). Built on native radio inputs. Give
 * it a name with `aria-labelledby` (a visible label) or `aria-label`. `description` shows a short text under it, for
 * example what the selected choice means.
 *
 * Accessibility: Tab moves into the group, the arrow keys change the choice. The description is linked to the group
 * with `aria-describedby` and is a polite live region: a change of the choice reads the new text once.
 */
const meta = {
  component: SegmentedControl<Kind>,
  render: (args) => <LabelledSegmentedControl {...args} />,
  args: {
    options,
    value: 'discussion',
    onChange: fn(),
  },
} satisfies Meta<StoryArgs>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('radiogroup', { name: LABEL })).not.toHaveAttribute('aria-describedby');
    await userEvent.tab();
    await expect(canvas.getByRole('radio', { name: 'Discussion' })).toHaveFocus();
    await userEvent.keyboard('{ArrowRight}');
    await expect(canvas.getByRole('radio', { name: 'Course' })).toBeChecked();
    await expect(args.onChange).toHaveBeenCalledWith('course');
  },
};

export const WithIcons: Story = {
  args: { options: optionsWithIcons },
};

export const WithDescription: Story = {
  args: { options: optionsWithIcons, describeChoice: true },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const group = canvas.getByRole('radiogroup', { name: LABEL });
    await expect(group).toHaveAccessibleDescription(descriptions.discussion);
    const description = canvas.getByText(descriptions.discussion);
    await expect(description).toHaveAttribute('aria-live', 'polite');

    await userEvent.tab();
    await userEvent.keyboard('{ArrowRight}');
    await expect(canvas.getByRole('radio', { name: 'Course' })).toBeChecked();
    // The same element holds the new text: the live region reads it, and the group keeps it as its description
    await expect(description).toHaveTextContent(descriptions.course);
    await expect(group).toHaveAccessibleDescription(descriptions.course);
  },
};

export const Disabled: Story = {
  args: { disabled: true, describeChoice: true },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('radiogroup', { name: LABEL })).toHaveAttribute('aria-disabled', 'true');
    for (const radio of canvas.getAllByRole('radio')) {
      await expect(radio).toBeDisabled();
    }
  },
};

export const LongLabels: Story = {
  args: {
    options: [
      { value: 'discussion', label: 'Discussion with every member' },
      { value: 'course', label: 'Course in an order' },
      { value: 'resources', label: 'Resources to download' },
    ],
    describeChoice: true,
  },
};
