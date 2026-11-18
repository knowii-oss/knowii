import type { MouseEvent } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, waitFor, within } from 'storybook/test';
import { FaArrowRightFromBracket, FaGear, FaUser } from 'react-icons/fa6';
import { Avatar } from './Avatar';
import type { DropdownMenuProps } from './DropdownMenu';
import { DropdownMenu, DropdownMenuItem, dropdownMenuItemClasses, DropdownMenuSeparator } from './DropdownMenu';

const LABEL = 'Account menu';

// Outside the application, a link of the menu must not leave the story: a click on it stops the visit. The menu renders
// in a portal, but its events go up the React tree to this handler. It runs after the handlers of the entry: Headless UI
// skips the handlers of an entry once the default action of the click is prevented.
function stopVisit(event: MouseEvent): void {
  if (event.target instanceof Element && event.target.closest('a')) {
    event.preventDefault();
  }
}

interface StoryArgs extends DropdownMenuProps {
  onProfile: () => void;
  onLogOut: () => void;
}

/**
 * The entries of an account menu: two links (in the application, Inertia links) and a button
 */
function AccountMenu({ onProfile, onLogOut, children: _children, ...args }: StoryArgs) {
  return (
    <div onClick={stopVisit} className="flex justify-end">
      <DropdownMenu {...args}>
        <DropdownMenuItem>
          <a href="/user/profile" className={dropdownMenuItemClasses} onClick={onProfile}>
            <FaUser aria-hidden="true" />
            Profile
          </a>
        </DropdownMenuItem>
        <DropdownMenuItem>
          <a href="/user/settings" className={dropdownMenuItemClasses}>
            <FaGear aria-hidden="true" />
            Settings
          </a>
        </DropdownMenuItem>
        <DropdownMenuSeparator className="mx-1 my-1 h-px bg-border" />
        <DropdownMenuItem>
          <button type="button" className={dropdownMenuItemClasses} onClick={onLogOut}>
            <FaArrowRightFromBracket aria-hidden="true" />
            Log out
          </button>
        </DropdownMenuItem>
      </DropdownMenu>
    </div>
  );
}

/**
 * A menu of actions or links behind a button, for the actions that a page does not show at once (the account menu, the
 * "More" of a post). For a choice of a value, use a `Select`. Each entry is a `DropdownMenuItem` that holds one link or
 * one button with `dropdownMenuItemClasses`; `DropdownMenuSeparator` splits the groups.
 *
 * Accessibility: a button that opens a menu (Headless UI). Its name is `label`: the trigger often shows only an avatar.
 * Enter, Space and the arrows open the menu, the arrows move, Enter activates, Escape closes, and the focus goes back to
 * the button. The header is not focusable.
 */
const meta = {
  component: DropdownMenu,
  render: (args) => <AccountMenu {...args} />,
  args: {
    label: LABEL,
    trigger: <Avatar name="Rose Gardener" />,
    // The render gives the entries of the account menu
    children: null,
    onProfile: fn(),
    onLogOut: fn(),
  },
  parameters: {
    // The menu opens under the button: the docs page gives the story room for it
    docs: { story: { inline: false, iframeHeight: '320px' } },
  },
} satisfies Meta<StoryArgs>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The keyboard path: Enter opens the menu, the arrows move, Enter activates the entry, and the focus goes back to the
 * button. Escape closes without an action.
 */
export const KeyboardPath: Story = {
  play: async ({ args, canvasElement, step }) => {
    const button = within(canvasElement).getByRole('button', { name: LABEL });
    // The menu renders outside the canvas (a portal on the body)
    const page = within(canvasElement.ownerDocument.body);

    await step('Enter opens the menu on the first entry', async () => {
      await userEvent.tab();
      await expect(button).toHaveFocus();
      await userEvent.keyboard('{Enter}');
      const menu = await page.findByRole('menu');
      await expect(within(menu).getAllByRole('menuitem')).toHaveLength(3);
      await expect(button).toHaveAttribute('aria-expanded', 'true');
    });

    await step('Escape closes the menu, and the focus goes back to the button', async () => {
      await userEvent.keyboard('{Escape}');
      await waitFor(() => expect(page.queryByRole('menu')).not.toBeInTheDocument());
      await waitFor(() => expect(button).toHaveFocus());
      await expect(args.onLogOut).not.toHaveBeenCalled();
    });

    await step('The Up arrow opens the menu on the last entry, and Enter activates it', async () => {
      await userEvent.keyboard('{ArrowUp}');
      const menu = await page.findByRole('menu');
      await waitFor(() => expect(within(menu).getByRole('menuitem', { name: 'Log out' })).toHaveAttribute('data-focus'));
      await userEvent.keyboard('{ArrowUp}');
      await waitFor(() => expect(within(menu).getByRole('menuitem', { name: 'Settings' })).toHaveAttribute('data-focus'));
      await userEvent.keyboard('{ArrowDown}');
      await userEvent.keyboard('{Enter}');
      await expect(args.onLogOut).toHaveBeenCalledOnce();
      await waitFor(() => expect(page.queryByRole('menu')).not.toBeInTheDocument());
      await waitFor(() => expect(button).toHaveFocus());
    });
  },
};

/**
 * A link of the menu: a click activates it and closes the menu (here the visit is stopped)
 */
export const ClickOnALink: Story = {
  play: async ({ args, canvasElement }) => {
    const document = canvasElement.ownerDocument;
    const page = within(document.body);
    await userEvent.click(within(canvasElement).getByRole('button', { name: LABEL }));
    const menu = await page.findByRole('menu');
    const profile = within(menu).getByRole('menuitem', { name: 'Profile' });
    await expect(profile).toHaveAttribute('href', '/user/profile');

    const address = document.location.href;
    await userEvent.click(profile);
    await expect(document.location.href).toBe(address);
    await expect(args.onProfile).toHaveBeenCalledOnce();
    await waitFor(() => expect(page.queryByRole('menu')).not.toBeInTheDocument());
  },
};

/**
 * The name and the address of the account above the entries. The header is not an entry: the arrows skip it.
 */
export const WithHeader: Story = {
  args: {
    header: (
      <div className="flex flex-col">
        <span className="font-bold">Rose Gardener</span>
        <span className="text-sm text-muted">rose@example.com</span>
      </div>
    ),
  },
  play: async ({ canvasElement }) => {
    const page = within(canvasElement.ownerDocument.body);
    await userEvent.click(within(canvasElement).getByRole('button', { name: LABEL }));
    const menu = await page.findByRole('menu');
    await expect(within(menu).getByText('rose@example.com')).toBeInTheDocument();
    await expect(within(menu).getAllByRole('menuitem')).toHaveLength(3);
    await expect(within(menu).getAllByRole('separator')).toHaveLength(2);
    // The open menu renders in a portal outside the landmarks of the page: the story closes it for the check of the page
    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(page.queryByRole('menu')).not.toBeInTheDocument());
  },
};

/**
 * The menu opens from the left edge of the button, for a button at the left of the page
 */
export const AlignedAtTheStart: Story = {
  args: { align: 'start', trigger: <span className="px-3 py-2 font-bold">More</span>, label: 'More actions' },
  render: (args) => (
    <div className="flex">
      <AccountMenu {...args} />
    </div>
  ),
};

export const OnPhone: Story = {
  globals: { viewport: { value: 'phone', isRotated: false } },
};
