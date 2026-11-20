import { useId, useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import type { InputLabelProps } from './InputLabel';
import { InputLabel } from './InputLabel';
import type { SegmentedControlOption } from './SegmentedControl';
import { SegmentedControl } from './SegmentedControl';
import { TextInput } from './TextInput';

type Visibility = 'public' | 'private';

const visibilityOptions: SegmentedControlOption<Visibility>[] = [
  { value: 'public', label: 'Public' },
  { value: 'private', label: 'Private' },
];

/**
 * The label above its field, as a form shows it: `htmlFor` takes the id of the field
 */
function LabelledField(args: InputLabelProps) {
  const id = useId();
  return (
    <div className="max-w-md">
      <InputLabel {...args} htmlFor={id} />
      <TextInput id={id} className="mt-1" placeholder="Knowii Community" />
    </div>
  );
}

/**
 * The label above a group of controls: the group names the id of the label in `aria-labelledby`
 */
function LabelledGroup(args: InputLabelProps) {
  const id = useId();
  const [visibility, setVisibility] = useState<Visibility>('public');
  return (
    <div className="max-w-md">
      <InputLabel {...args} id={id} />
      <SegmentedControl<Visibility>
        aria-labelledby={id}
        className="mt-1"
        options={visibilityOptions}
        value={visibility}
        onChange={setVisibility}
      />
    </div>
  );
}

/**
 * The visible label of a form field, above the field. Give it `htmlFor` with the id of the field. For a group of
 * controls that is not one field (the radios of a `SegmentedControl`), give it an `id` and name that id in
 * `aria-labelledby` of the group. `value` comes first; the children show only without it.
 *
 * Not a heading, not a hint under a field, and not the `legend` of a `fieldset`.
 *
 * Accessibility: a `label`. With `htmlFor`, the label is the name of the field, and a click on it puts the focus in
 * the field. It takes no focus.
 */
const meta = {
  component: InputLabel,
  args: {
    children: 'Name',
  },
} satisfies Meta<typeof InputLabel>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The text as the `value` property
 */
export const WithValue: Story = {
  args: { value: 'Name', children: undefined },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByText('Name').tagName).toBe('LABEL');
  },
};

/**
 * With both, `value` shows and the children do not
 */
export const ValueBeforeChildren: Story = {
  args: { value: 'Name', children: 'Another text' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByText('Name').tagName).toBe('LABEL');
    await expect(canvas.queryByText('Another text')).toBeNull();
  },
};

/**
 * The text as children, for a label that is more than a string
 */
export const WithChildren: Story = {
  args: {
    children: (
      <>
        Description <span className="font-normal">(optional)</span>
      </>
    ),
  },
  play: async ({ canvasElement }) => {
    const label = canvasElement.querySelector('label');
    await expect(label).toHaveTextContent('Description (optional)');
  },
};

/**
 * With `htmlFor`, the label is the name of the field, and a click on it puts the focus in the field
 */
export const NamesAField: Story = {
  render: (args) => <LabelledField {...args} />,
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const field = canvas.getByRole('textbox', { name: 'Name' });
    await expect(field).toHaveAccessibleName('Name');
    // The field is the control of the label: this is what sends a click on the label to the field
    await expect(canvas.getByText<HTMLLabelElement>('Name').control).toBe(field);
  },
};

/**
 * With an `id`, the label is the name of a group of controls that names it in `aria-labelledby`
 */
export const NamesAGroup: Story = {
  args: { children: 'Community visibility' },
  render: (args) => <LabelledGroup {...args} />,
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const group = canvas.getByRole('radiogroup', { name: 'Community visibility' });
    await expect(group).toHaveAttribute('aria-labelledby', canvas.getByText('Community visibility').getAttribute('id'));
    await userEvent.tab();
    await expect(within(group).getByRole('radio', { name: 'Public' })).toHaveFocus();
  },
};
