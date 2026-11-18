import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import type { SaveState } from './SaveStatus';
import { SaveStatus } from './SaveStatus';
import type { SwitchProps } from './Switch';
import { Switch } from './Switch';

/**
 * The switch with its state, as a page holds it: the story args set the value at the start
 */
function ControlledSwitch({ checked: checkedAtStart, onChange, ...args }: SwitchProps) {
  const [checked, setChecked] = useState(checkedAtStart);
  return (
    <div className="max-w-md">
      <Switch
        {...args}
        checked={checked}
        onChange={(next) => {
          setChecked(next);
          onChange(next);
        }}
      />
    </div>
  );
}

/**
 * A setting that saves at once: the switch shows the new value, is busy for a moment, and the status says "Saving"
 * then "Saved"
 */
function SavingSwitch(args: SwitchProps) {
  const [checked, setChecked] = useState(args.checked);
  const [busy, setBusy] = useState(false);
  const [saveState, setSaveState] = useState<SaveState>({ status: 'idle' });
  return (
    <div className="max-w-md">
      <Switch
        {...args}
        checked={checked}
        busy={busy}
        onChange={(next) => {
          setChecked(next);
          setBusy(true);
          setSaveState({ status: 'saving' });
          setTimeout(() => {
            setBusy(false);
            setSaveState({ status: 'saved' });
            args.onChange(next);
          }, 300);
        }}
        status={<SaveStatus state={saveState} />}
      />
    </div>
  );
}

/**
 * A switch turns one setting on or off, and the change applies at once, without a "Save" button. Next to it, a
 * `SaveStatus` says "Saving", then "Saved", or the error (the switch shows the old value again). In a form with a
 * "Save" button, use a `Checkbox` instead. A change that widens access asks first, with a `Dialog`.
 *
 * Accessibility: a button with role="switch" and aria-checked, named by the label and described by the description
 * (aria-describedby). Space and a click (also on the label) toggle it. A busy switch keeps the focus: it gets
 * aria-busy and aria-disabled, and ignores a click and Space. On a phone, the target is 44 px.
 */
const meta = {
  component: Switch,
  render: (args) => <ControlledSwitch {...args} />,
  args: {
    checked: false,
    onChange: fn(),
    label: 'Show pop-ups',
    description: 'A short message when something new needs you. Nothing else pops up.',
  },
} satisfies Meta<typeof Switch>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The keyboard path: Tab gives the focus, Space turns it on, Space again turns it off
 */
export const Off: Story = {
  play: async ({ args, canvasElement }) => {
    const toggle = within(canvasElement).getByRole('switch', { name: 'Show pop-ups' });
    await expect(toggle).toHaveAccessibleDescription('A short message when something new needs you. Nothing else pops up.');
    await userEvent.tab();
    await expect(toggle).toHaveFocus();
    await userEvent.keyboard(' ');
    await expect(toggle).toBeChecked();
    await userEvent.keyboard(' ');
    await expect(toggle).not.toBeChecked();
    await expect(args.onChange).toHaveBeenCalledTimes(2);
  },
};

export const On: Story = {
  args: { checked: true },
};

export const WithoutDescription: Story = {
  args: { description: undefined },
};

/**
 * The value is being saved: the switch keeps the focus and ignores Space and a click
 */
export const Busy: Story = {
  args: { checked: true, busy: true },
  play: async ({ args, canvasElement }) => {
    const toggle = within(canvasElement).getByRole('switch', { name: 'Show pop-ups' });
    await userEvent.tab();
    await userEvent.keyboard(' ');
    await userEvent.click(toggle);
    await expect(toggle).toHaveFocus();
    await expect(toggle).toBeChecked();
    await expect(toggle).toHaveAttribute('aria-busy', 'true');
    await expect(args.onChange).not.toHaveBeenCalled();
  },
};

export const Disabled: Story = {
  args: { disabled: true },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('switch', { name: 'Show pop-ups' })).toBeDisabled();
  },
};

/**
 * A setting that saves at once, with its status: "Saving", then "Saved"
 */
export const WithSaveStatus: Story = {
  render: (args) => <SavingSwitch {...args} />,
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    const toggle = canvas.getByRole('switch', { name: 'Show pop-ups' });
    await userEvent.click(toggle);
    await expect(canvas.getByRole('status')).toHaveTextContent('Saving');
    await expect(await canvas.findByText('Saved')).toBeInTheDocument();
    await expect(toggle).toHaveFocus();
    await expect(args.onChange).toHaveBeenCalledWith(true);
  },
};

export const LongText: Story = {
  args: {
    label: 'Search engines may index the public spaces and the public page of the community',
    description:
      'Off by default. When this is on, the public spaces that anyone can read, and the public page of the community, may appear in search engines, with the names and pictures of their authors. Members-only spaces and chat spaces are never indexed.',
  },
};
