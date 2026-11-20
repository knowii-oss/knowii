import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { FaBook, FaComments, FaGraduationCap } from 'react-icons/fa6';
import type { SegmentedControlProps } from './SegmentedControl';
import { SegmentedControl } from './SegmentedControl';

type Kind = 'discussion' | 'course' | 'resources';

const LABEL = 'Kind of space';
// Why one option cannot be chosen, in the story "One option not available"
const FULL = 'No course is left in your plan.';
const FULL_ID = 'segmented-control-reason';

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
  const [value, setValue] = useState<Kind | null>(valueAtStart);
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
        {...(describeChoice && value !== null ? { description: descriptions[value] } : {})}
      />
      {/* The reason of an option that cannot be chosen is a text, never a tooltip */}
      {args.options.some((option) => option.ariaDisabled) && (
        <p id={FULL_ID} className="text-sm text-muted">
          {FULL}
        </p>
      )}
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
 *
 * Three more states. `value` null: no choice yet, no radio is checked, and Tab stops on the first radio. An option
 * with `ariaDisabled` cannot be chosen now: its radio has `aria-disabled`, never `disabled`, so it keeps the focus and
 * its place in the arrow order, and a click, Space or an arrow key on it changes nothing; `describedBy` names the text
 * that says why, which the caller shows next to the control. `busy` sets `aria-busy` on the group while a change is
 * saved: the radios stay enabled and the focus stays where it is.
 *
 * Forced colors: the chosen option has a border in the system color `Highlight`, because its fill is lost there.
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
    // The mark of the choice in forced colors, where the fill is lost
    await expect(canvas.getByRole('radio', { name: 'Discussion' }).closest('label')).toHaveClass(
      'forced-colors:has-checked:border-[color:Highlight]',
    );
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

export const NoChoice: Story = {
  args: { value: null },
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    for (const radio of canvas.getAllByRole('radio')) {
      await expect(radio).not.toBeChecked();
    }
    // One tab stop: the first radio, then the focus leaves the group
    await userEvent.tab();
    await expect(canvas.getByRole('radio', { name: 'Discussion' })).toHaveFocus();
    await userEvent.tab();
    await expect(canvasElement.contains(document.activeElement) && document.activeElement?.getAttribute('type') === 'radio').toBe(false);

    await userEvent.click(canvas.getByRole('radio', { name: 'Resources' }));
    await expect(canvas.getByRole('radio', { name: 'Resources' })).toBeChecked();
    await expect(args.onChange).toHaveBeenCalledWith('resources');
  },
};

export const OneOptionNotAvailable: Story = {
  args: {
    options: [
      { value: 'discussion', label: 'Discussion' },
      { value: 'course', label: 'Course', ariaDisabled: true, describedBy: FULL_ID },
      { value: 'resources', label: 'Resources' },
    ],
  },
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    const course = canvas.getByRole('radio', { name: 'Course' });
    await expect(course).toHaveAttribute('aria-disabled', 'true');
    await expect(course).not.toBeDisabled();
    await expect(course).toHaveAccessibleDescription(FULL);
    await expect(canvas.getByRole('radio', { name: 'Discussion' })).not.toHaveAttribute('aria-disabled');

    // A click changes nothing
    await userEvent.click(course);
    await expect(course).not.toBeChecked();
    await expect(canvas.getByRole('radio', { name: 'Discussion' })).toBeChecked();
    await expect(args.onChange).not.toHaveBeenCalled();

    // An arrow key reaches it and changes nothing; the next arrow key chooses the option after it
    canvas.getByRole('radio', { name: 'Discussion' }).focus();
    await userEvent.keyboard('{ArrowRight}');
    await expect(course).not.toBeChecked();
    await expect(canvas.getByRole('radio', { name: 'Discussion' })).toBeChecked();
    await expect(args.onChange).not.toHaveBeenCalled();
    await userEvent.keyboard('{ArrowRight}');
    await expect(canvas.getByRole('radio', { name: 'Resources' })).toBeChecked();
    await expect(args.onChange).toHaveBeenCalledWith('resources');
  },
};

export const Busy: Story = {
  args: { busy: true },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('radiogroup', { name: LABEL })).toHaveAttribute('aria-busy', 'true');
    for (const radio of canvas.getAllByRole('radio')) {
      await expect(radio).not.toBeDisabled();
    }
    // The focus stays in the group
    await userEvent.tab();
    await expect(canvas.getByRole('radio', { name: 'Discussion' })).toHaveFocus();
  },
};
