import type { MouseEvent } from 'react';
import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { FaArrowRight, FaPlus } from 'react-icons/fa6';
import type { ButtonProps } from './Button';
import { Button, LinkButton } from './Button';
import { TextInput } from './TextInput';

/**
 * Use a button for an action on the page. For navigation, use `LinkButton` (a link that looks like a button), never a
 * button inside a link. The default type is "button": set `type="submit"` to submit a form.
 *
 * Accessibility: the name is the text, or `aria-label` for a button with only an icon. A button with `disabled` is out
 * of the tab order. A busy button keeps the focus: use `loading` (it sets `aria-busy` and `aria-disabled`, and ignores a
 * click and the submit of a form). A button that becomes disabled while it has the focus also keeps it: it gets
 * `aria-disabled` until the focus leaves it. For a button that does nothing for another reason, use `aria-disabled` and
 * a guard in the click handler.
 */
const meta = {
  component: Button,
  args: {
    children: 'Save',
    onClick: fn(),
  },
} satisfies Meta<typeof Button>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Primary: Story = {
  args: { variant: 'primary' },
  play: async ({ args, canvasElement }) => {
    const button = within(canvasElement).getByRole('button', { name: 'Save' });
    await userEvent.tab();
    await expect(button).toHaveFocus();
    await userEvent.keyboard('{Enter}');
    await expect(args.onClick).toHaveBeenCalledOnce();
  },
};

export const Secondary: Story = {
  args: { variant: 'secondary' },
};

export const Danger: Story = {
  args: { variant: 'danger', children: 'Delete' },
};

export const Ghost: Story = {
  args: { variant: 'ghost', children: 'Cancel' },
};

export const Small: Story = {
  args: { size: 'sm' },
};

export const Large: Story = {
  args: { size: 'lg' },
};

export const WithIconBefore: Story = {
  args: { leftIcon: <FaPlus aria-hidden="true" />, children: 'New post' },
};

export const WithIconAfter: Story = {
  args: { rightIcon: <FaArrowRight aria-hidden="true" />, children: 'Next' },
};

export const IconOnly: Story = {
  args: { iconOnly: true, variant: 'ghost', 'aria-label': 'New post', children: <FaPlus aria-hidden="true" /> },
};

export const FullWidth: Story = {
  args: { fullWidth: true },
};

export const LongText: Story = {
  args: { children: 'Save the changes of the community and tell every member about them' },
};

/**
 * `disabled` takes the button out of the tab order. A pointer click gives it no focus.
 */
export const Disabled: Story = {
  args: { disabled: true },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('button', { name: 'Save' })).toBeDisabled();
  },
};

function SendAndClear({ onClick, ...props }: ButtonProps) {
  const [text, setText] = useState('Hello');
  return (
    <form
      className="flex items-end gap-2"
      onSubmit={(event) => {
        event.preventDefault();
        setText('');
      }}
    >
      <div className="flex flex-col gap-1">
        <label htmlFor="story-comment" className="text-sm font-bold">
          Comment
        </label>
        <TextInput id="story-comment" value={text} onChange={(event) => setText(event.target.value)} />
      </div>
      <Button {...props} type="submit" disabled={text.trim() === ''} onClick={onClick} />
    </form>
  );
}

/**
 * A send clears the field, so the button becomes disabled while it has the focus (#1618). It keeps the focus with
 * `aria-disabled`, and Enter does nothing. When the focus leaves it, it is really disabled.
 */
export const DisabledWhileFocused: Story = {
  args: { children: 'Send' },
  render: (args) => <SendAndClear {...args} />,
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    const field = canvas.getByRole('textbox', { name: 'Comment' });
    const button = canvas.getByRole('button', { name: 'Send' });
    await userEvent.click(field);
    await userEvent.tab();
    await expect(button).toHaveFocus();
    await userEvent.keyboard('{Enter}');
    await expect(args.onClick).toHaveBeenCalledOnce();
    await expect(field).toHaveValue('');
    await expect(button).toHaveFocus();
    await expect(button).toBeEnabled();
    await expect(button).toHaveAttribute('aria-disabled', 'true');
    await userEvent.keyboard('{Enter}');
    await expect(args.onClick).toHaveBeenCalledOnce();
    await userEvent.tab({ shift: true });
    await expect(field).toHaveFocus();
    await expect(button).toBeDisabled();
  },
};

/**
 * `aria-disabled` keeps the button in the tab order. The click handler needs a guard: Enter and Space still click it.
 */
export const AriaDisabled: Story = {
  args: { 'aria-disabled': true },
  render: ({ onClick, ...args }) => (
    <Button
      {...args}
      onClick={(event) => {
        if (event.currentTarget.getAttribute('aria-disabled') === 'true') {
          return;
        }
        onClick?.(event);
      }}
    />
  ),
  play: async ({ args, canvasElement }) => {
    const button = within(canvasElement).getByRole('button', { name: 'Save' });
    await userEvent.tab();
    await expect(button).toHaveFocus();
    await userEvent.keyboard('{Enter}');
    await expect(args.onClick).not.toHaveBeenCalled();
  },
};

/**
 * `loading` shows a spinner and sets `aria-busy` and `aria-disabled`. The button stays in the tab order, and a click,
 * Enter or Space does nothing.
 */
export const Busy: Story = {
  args: { loading: true },
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    const button = canvas.getByRole('button', { name: /Save/ });
    await expect(button).toBeEnabled();
    await expect(button).toHaveAttribute('aria-busy', 'true');
    await expect(button).toHaveAttribute('aria-disabled', 'true');
    await expect(canvas.getByRole('status')).toHaveTextContent('Loading');
    await userEvent.tab();
    await expect(button).toHaveFocus();
    await userEvent.keyboard('{Enter}');
    await expect(args.onClick).not.toHaveBeenCalled();
    await expect(button).toHaveFocus();
  },
};

function SaveThenBusy({ onClick, ...props }: ButtonProps) {
  const [loading, setLoading] = useState(false);
  return (
    <Button
      {...props}
      loading={loading}
      onClick={(event) => {
        setLoading(true);
        onClick?.(event);
      }}
    />
  );
}

/**
 * The button becomes busy when it is pressed, and keeps the focus (#1564): the next Tab goes on from it.
 */
export const BusyKeepsFocus: Story = {
  render: (args) => <SaveThenBusy {...args} />,
  play: async ({ args, canvasElement }) => {
    const button = within(canvasElement).getByRole('button', { name: 'Save' });
    await userEvent.tab();
    await expect(button).toHaveFocus();
    await userEvent.keyboard('{Enter}');
    await expect(args.onClick).toHaveBeenCalledOnce();
    await expect(button).toHaveAttribute('aria-busy', 'true');
    await expect(button).toHaveFocus();
    // While busy, the button does nothing
    await userEvent.keyboard('{Enter}');
    await expect(args.onClick).toHaveBeenCalledOnce();
    await expect(button).toHaveFocus();
  },
};

// Outside the application, there is no Inertia page to visit: the click stops the visit
// (Inertia does not visit when the default action of the click is prevented)
function stopVisit(event: MouseEvent): void {
  event.preventDefault();
}

/**
 * `LinkButton`: an Inertia link that looks like a button, for navigation.
 */
export const Link: Story = {
  args: { variant: 'secondary', children: 'All communities' },
  render: ({ variant, size, children }) => (
    <LinkButton href="/communities" variant={variant} size={size} onClick={stopVisit}>
      {children}
    </LinkButton>
  ),
  play: async ({ canvasElement }) => {
    const link = within(canvasElement).getByRole('link', { name: 'All communities' });
    await expect(link).toHaveAttribute('href', '/communities');
    const address = canvasElement.ownerDocument.location.href;
    await userEvent.click(link);
    await expect(canvasElement.ownerDocument.location.href).toBe(address);
  },
};

/**
 * `LinkButton` with `method="post"`: Inertia renders a button that sends the request.
 */
export const LinkWithPost: Story = {
  args: { variant: 'danger', children: 'Log out' },
  render: ({ variant, size, children }) => (
    <LinkButton href="/logout" method="post" variant={variant} size={size} onClick={stopVisit}>
      {children}
    </LinkButton>
  ),
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('button', { name: 'Log out' })).toBeInTheDocument();
  },
};
