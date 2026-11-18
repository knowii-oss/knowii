import { useId, useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, waitFor, within } from 'storybook/test';
import { FaCarrot, FaLeaf, FaSeedling } from 'react-icons/fa6';
import type { SelectOption, SelectProps } from './Select';
import { Select } from './Select';

type Season = 'spring' | 'summer' | 'autumn' | 'winter';

const LABEL = 'Season';
const ERROR_TEXT = 'Choose the season of the planting.';

const options: readonly SelectOption<Season>[] = [
  { value: 'spring', label: 'Spring' },
  { value: 'summer', label: 'Summer' },
  { value: 'autumn', label: 'Autumn' },
  { value: 'winter', label: 'Winter' },
];

const optionsWithIcons: readonly SelectOption<Season>[] = [
  { value: 'spring', label: 'Spring', icon: <FaSeedling /> },
  { value: 'summer', label: 'Summer', icon: <FaCarrot /> },
  { value: 'autumn', label: 'Autumn', icon: <FaLeaf /> },
];

interface StoryArgs extends SelectProps<Season> {
  /**
   * Show a visible label (`<label htmlFor>`); without it, the select needs `aria-label`
   */
  withLabel?: boolean;
}

/**
 * The select with its label and its value, as a form shows it. With `invalid`, the error text shows under it.
 */
function LabelledSelect({ value: valueAtStart, onChange, withLabel = true, ...args }: StoryArgs) {
  const id = useId();
  const [value, setValue] = useState<Season | null>(valueAtStart);
  return (
    <div className="flex max-w-md flex-col gap-1">
      {withLabel && (
        <label htmlFor={id} className="text-sm font-bold">
          {LABEL}
        </label>
      )}
      <Select<Season>
        {...args}
        id={id}
        value={value}
        onChange={(next) => {
          setValue(next);
          onChange(next);
        }}
      />
      {/* Select takes no aria-describedby: the error text cannot be linked to it */}
      {args.invalid && <p className="text-sm text-danger-text">{ERROR_TEXT}</p>}
    </div>
  );
}

/**
 * A choice of one option in a list that opens, when the options are too many to show side by side (more than three or
 * four). For a few short options, use a `SegmentedControl` or radios: they show every choice at once. Give it a label
 * (`<label htmlFor={id}>`) or `aria-label`. A value that matches no option shows the placeholder.
 *
 * Accessibility: a button that opens a listbox (Headless UI). The arrow keys, Home, End and the first letters move in
 * the list, Enter chooses, Escape closes, and the focus goes back to the button. `invalid` sets `aria-invalid`. The
 * icons are hidden from screen readers.
 */
const meta = {
  component: Select<Season>,
  render: (args) => <LabelledSelect {...args} />,
  args: {
    options,
    value: null,
    onChange: fn(),
    placeholder: 'Choose a season',
  },
  parameters: {
    // The list opens under the button: the docs page gives the story room for it
    docs: { story: { inline: false, iframeHeight: '320px' } },
  },
} satisfies Meta<StoryArgs>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The keyboard path: Tab gives the focus to the button, the Down arrow opens the list, Enter chooses, Escape closes
 * without a change, and the focus goes back to the button
 */
export const Default: Story = {
  play: async ({ args, canvasElement, step }) => {
    const button = within(canvasElement).getByRole('button', { name: new RegExp(LABEL) });
    // The list renders outside the canvas (a portal on the body)
    const page = within(canvasElement.ownerDocument.body);
    await expect(button).toHaveTextContent('Choose a season');

    await step('The Down arrow opens the list, Enter chooses', async () => {
      await userEvent.tab();
      await expect(button).toHaveFocus();
      await userEvent.keyboard('{ArrowDown}');
      await expect(await page.findByRole('listbox')).toBeInTheDocument();
      await userEvent.keyboard('{ArrowDown}');
      await userEvent.keyboard('{Enter}');
      await waitFor(() => expect(page.queryByRole('listbox')).not.toBeInTheDocument());
      await expect(button).toHaveTextContent('Summer');
      await expect(args.onChange).toHaveBeenCalledWith('summer');
      await waitFor(() => expect(button).toHaveFocus());
    });

    await step('Escape closes the list without a change', async () => {
      await userEvent.keyboard('{ArrowDown}');
      const listbox = await page.findByRole('listbox');
      await expect(within(listbox).getByRole('option', { name: 'Summer' })).toHaveAttribute('aria-selected', 'true');
      await userEvent.keyboard('{End}');
      await userEvent.keyboard('{Escape}');
      await waitFor(() => expect(page.queryByRole('listbox')).not.toBeInTheDocument());
      await expect(button).toHaveTextContent('Summer');
      await expect(args.onChange).toHaveBeenCalledOnce();
      await waitFor(() => expect(button).toHaveFocus());
    });
  },
};

export const WithValue: Story = {
  args: { value: 'autumn' },
  play: async ({ canvasElement }) => {
    const button = within(canvasElement).getByRole('button', { name: new RegExp(LABEL) });
    await expect(button).toHaveTextContent('Autumn');
    await userEvent.click(button);
    const option = await within(canvasElement.ownerDocument.body).findByRole('option', { name: 'Autumn' });
    await expect(option).toHaveAttribute('aria-selected', 'true');
    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(within(canvasElement.ownerDocument.body).queryByRole('listbox')).not.toBeInTheDocument());
  },
};

export const WithIcons: Story = {
  args: { options: optionsWithIcons, value: 'spring' },
};

export const WithoutVisibleLabel: Story = {
  args: { withLabel: false, 'aria-label': LABEL },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('button', { name: LABEL })).toBeInTheDocument();
  },
};

/**
 * The value is refused: the button gets `aria-invalid`. The error text shows under it, but it is not linked to the
 * button (Select has no `aria-describedby`)
 */
export const WithError: Story = {
  args: { invalid: true },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('button', { name: new RegExp(LABEL) })).toHaveAttribute('aria-invalid', 'true');
  },
};

export const Disabled: Story = {
  args: { disabled: true, value: 'winter' },
  play: async ({ canvasElement }) => {
    const button = within(canvasElement).getByRole('button', { name: new RegExp(LABEL) });
    await expect(button).toBeDisabled();
    await userEvent.click(button);
    await expect(within(canvasElement.ownerDocument.body).queryByRole('listbox')).toBeNull();
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
    options: [
      { value: 'spring', label: 'Spring: sow the seeds inside, and plant the seedlings out after the last frost' },
      { value: 'summer', label: 'Summer: water at the foot, in the morning, and pick the fruits when they are ripe' },
      { value: 'autumn', label: 'Autumn: keep the seeds dry, and cover the beds with leaves for the winter' },
      { value: 'winter', label: 'Winter: plan the beds of the next year and order the seeds' },
    ],
    value: 'spring',
  },
};
