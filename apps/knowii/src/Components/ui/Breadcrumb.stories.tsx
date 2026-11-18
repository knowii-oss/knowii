import type { MouseEvent } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { FaHouse } from 'react-icons/fa6';
import type { BreadcrumbItem } from './Breadcrumb';
import { Breadcrumb } from './Breadcrumb';

// Outside the application, there is no page to visit: a click on a link of the path stops the visit (the link is a plain
// <a> without a ToolkitProvider; Inertia does not visit either when the default action is prevented, see Button)
function stopVisit(event: MouseEvent): void {
  event.preventDefault();
}

const shortPath: readonly BreadcrumbItem[] = [
  { label: 'Home', href: '/dashboard' },
  { label: 'Atlas Garden', href: '/communities/atlas-garden' },
  { label: 'Settings' },
];

const longPath: readonly BreadcrumbItem[] = [
  { label: 'Home', href: '/dashboard' },
  { label: 'Atlas Garden', href: '/communities/atlas-garden' },
  { label: 'Vegetables', href: '/communities/atlas-garden/vegetables' },
  { label: 'Tomatoes', href: '/communities/atlas-garden/vegetables/tomatoes' },
  { label: 'When to plant the seedlings' },
];

/**
 * The path from the start page to the open page, at the top of the page. Every item but the last one is a link; the
 * last one is the open page. On a phone, a path of more than three items shows the first item, a "Show the full path"
 * button, the parent and the open page (#909).
 *
 * Accessibility: a navigation landmark named "Breadcrumb", with an ordered list. The open page has
 * `aria-current="page"` and is not a link. The icons and the separators are hidden from screen readers. The button
 * shows the hidden items and gives the focus to the link of the first of them, or to the list when that item has no
 * link. On a small screen, the links are 44 px high.
 */
const meta = {
  component: Breadcrumb,
  render: (args) => (
    <div onClickCapture={stopVisit}>
      <Breadcrumb {...args} />
    </div>
  ),
  args: {
    items: shortPath,
  },
} satisfies Meta<typeof Breadcrumb>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  play: async ({ canvasElement }) => {
    const nav = within(canvasElement).getByRole('navigation', { name: 'Breadcrumb' });
    const breadcrumb = within(nav);
    await expect(breadcrumb.getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/dashboard');
    await expect(breadcrumb.getByRole('link', { name: 'Atlas Garden' })).toHaveAttribute('href', '/communities/atlas-garden');
    await expect(breadcrumb.queryByRole('link', { name: 'Settings' })).toBeNull();
    await expect(breadcrumb.getByText('Settings').closest('[aria-current]')).toHaveAttribute('aria-current', 'page');
    await expect(breadcrumb.queryByRole('button', { name: 'Show the full path' })).toBeNull();

    // The keyboard reaches the links, not the open page
    await userEvent.tab();
    await expect(breadcrumb.getByRole('link', { name: 'Home' })).toHaveFocus();
    await userEvent.tab();
    await expect(breadcrumb.getByRole('link', { name: 'Atlas Garden' })).toHaveFocus();
    const address = canvasElement.ownerDocument.location.href;
    await userEvent.keyboard('{Enter}');
    await expect(canvasElement.ownerDocument.location.href).toBe(address);
  },
};

export const WithIcon: Story = {
  args: {
    items: [{ label: 'Home', href: '/dashboard', icon: <FaHouse /> }, ...shortPath.slice(1)],
  },
  play: async ({ canvasElement }) => {
    const home = within(canvasElement).getByRole('link', { name: 'Home' });
    await expect(home.querySelector('[aria-hidden="true"] svg')).not.toBeNull();
  },
};

export const OnlyTheOpenPage: Story = {
  args: { items: [{ label: 'Home' }] },
  play: async ({ canvasElement }) => {
    const nav = within(canvasElement).getByRole('navigation', { name: 'Breadcrumb' });
    await expect(within(nav).queryByRole('link')).toBeNull();
    await expect(within(nav).getByText('Home').closest('[aria-current]')).toHaveAttribute('aria-current', 'page');
  },
};

/**
 * An item without `href` is text, not a link: here the community, which the member may not open
 */
export const ItemWithoutLink: Story = {
  args: {
    items: [{ label: 'Home', href: '/dashboard' }, { label: 'Atlas Garden' }, { label: 'Settings' }],
  },
  play: async ({ canvasElement }) => {
    const nav = within(canvasElement).getByRole('navigation', { name: 'Breadcrumb' });
    await expect(within(nav).getAllByRole('link')).toHaveLength(1);
    await expect(within(nav).getByText('Atlas Garden').closest('[aria-current]')).toBeNull();
  },
};

/**
 * A long path on a large screen: every item shows
 */
export const LongPath: Story = {
  args: { items: longPath },
};

/**
 * A long path on a phone: the middle of the path is hidden until the member asks for it, and then the focus goes to the
 * first item that was hidden
 */
export const LongPathOnPhone: Story = {
  args: { items: longPath },
  globals: { viewport: { value: 'phone', isRotated: false } },
  play: async ({ canvasElement }) => {
    const breadcrumb = within(within(canvasElement).getByRole('navigation', { name: 'Breadcrumb' }));
    // Only the items between the first one and the parent of the open page are hidden
    await expect(breadcrumb.getByRole('link', { name: 'Atlas Garden' }).closest('li')).toHaveAttribute('data-collapsed');
    await expect(breadcrumb.getByRole('link', { name: 'Vegetables' }).closest('li')).toHaveAttribute('data-collapsed');
    await expect(breadcrumb.getByRole('link', { name: 'Tomatoes' }).closest('li')).not.toHaveAttribute('data-collapsed');

    await userEvent.click(breadcrumb.getByRole('button', { name: 'Show the full path' }));

    await expect(breadcrumb.queryByRole('button', { name: 'Show the full path' })).toBeNull();
    await expect(breadcrumb.getByRole('link', { name: 'Atlas Garden' }).closest('li')).not.toHaveAttribute('data-collapsed');
    await expect(breadcrumb.getByRole('link', { name: 'Atlas Garden' })).toHaveFocus();
  },
};

/**
 * A long path on a phone where the first hidden item is not a link: after "Show the full path", the focus goes to the
 * list of the path (a screen reader reads it from its start), not to the page
 */
export const LongPathOnPhoneWithoutLink: Story = {
  args: {
    items: [
      { label: 'Home', href: '/dashboard' },
      { label: 'Atlas Garden' },
      { label: 'Vegetables', href: '/communities/atlas-garden/vegetables' },
      { label: 'Tomatoes', href: '/communities/atlas-garden/vegetables/tomatoes' },
      { label: 'When to plant the seedlings' },
    ],
  },
  globals: { viewport: { value: 'phone', isRotated: false } },
  play: async ({ canvasElement }) => {
    const breadcrumb = within(within(canvasElement).getByRole('navigation', { name: 'Breadcrumb' }));
    await userEvent.click(breadcrumb.getByRole('button', { name: 'Show the full path' }));
    await expect(breadcrumb.getByText('Atlas Garden').closest('li')).not.toHaveAttribute('data-collapsed');
    // The first item that the button showed has no link: the focus goes to the list, not to the page
    await expect(breadcrumb.getByRole('list')).toHaveFocus();
  },
};

/**
 * Long names are cut with an ellipsis; the open page can use the full line on a phone
 */
export const LongText: Story = {
  args: {
    items: [
      { label: 'Home', href: '/dashboard' },
      { label: 'The community of the gardeners who grow old seeds and share them every spring', href: '/communities/seeds' },
      { label: 'How to keep the seeds of tomatoes, beans and peppers dry and safe from one year to the next one' },
    ],
  },
};
