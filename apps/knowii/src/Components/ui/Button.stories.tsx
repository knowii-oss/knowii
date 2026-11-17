import type { MouseEvent } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { FaArrowRight, FaPlus } from 'react-icons/fa6';
import { Button, LinkButton } from './Button';

/**
 * Use a button for an action on the page. For navigation, use `LinkButton` (a link that looks like a button), never a
 * button inside a link. The default type is "button": set `type="submit"` to submit a form.
 *
 * Accessibility: the name is the text, or `aria-label` for a button with only an icon. A button with `disabled` or
 * `loading` loses the focus: when the focus must stay (a button that works and then works again), use `aria-busy`
 * or `aria-disabled` and a guard in the click handler.
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
 * `disabled` takes the button out of the tab order: use it only when the focus is not on the button.
 */
export const Disabled: Story = {
  args: { disabled: true },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('button', { name: 'Save' })).toBeDisabled();
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
 * `loading` shows a spinner, sets `aria-busy` and disables the button.
 */
export const Busy: Story = {
  args: { loading: true },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const button = canvas.getByRole('button', { name: /Save/ });
    await expect(button).toBeDisabled();
    await expect(button).toHaveAttribute('aria-busy', 'true');
    await expect(canvas.getByRole('status')).toHaveTextContent('Loading');
  },
};

/**
 * Busy without losing the focus: `aria-busy` and `aria-disabled`, and a guard in the click handler.
 */
export const BusyKeepsFocus: Story = {
  args: { 'aria-busy': true, 'aria-disabled': true, children: 'Saving' },
  render: ({ onClick, ...args }) => (
    <Button
      {...args}
      onClick={(event) => {
        if (event.currentTarget.getAttribute('aria-busy') === 'true') {
          return;
        }
        onClick?.(event);
      }}
    />
  ),
  play: async ({ args, canvasElement }) => {
    const button = within(canvasElement).getByRole('button', { name: 'Saving' });
    await userEvent.tab();
    await expect(button).toHaveFocus();
    await userEvent.keyboard('{Enter}');
    await expect(args.onClick).not.toHaveBeenCalled();
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
