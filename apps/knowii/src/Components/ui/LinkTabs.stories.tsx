import type { MouseEvent } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import type { LinkTabItem } from './LinkTabs';
import { LinkTabs } from './LinkTabs';

// Outside the application, there is no page to visit: a click on a link stops the visit (see Breadcrumb)
function stopVisit(event: MouseEvent): void {
  event.preventDefault();
}

const LABEL = 'Events';

const views: readonly LinkTabItem[] = [
  { label: 'Upcoming', href: '/communities/atlas-garden/events', current: true },
  { label: 'Past', href: '/communities/atlas-garden/events?view=past' },
];

/**
 * A row of links to the views of one page, where each view has its own address: "Upcoming" and "Past" of the events.
 * Each link loads its view. For a choice that changes the page without a new address, use a `SegmentedControl`; for
 * the path to the open page, use a `Breadcrumb`.
 *
 * Accessibility: a navigation landmark with a name (`ariaLabel`) and a list of links. The link of the open view has
 * `aria-current="page"`, a line under it and a heavier text: the marker is never a color alone. It is not the tabs of
 * ARIA: no `tablist`, no arrow keys. Tab goes from link to link and Enter follows a link. On a small screen the links
 * are 44 px high, and they wrap to more lines when the row is too long.
 */
const meta = {
  component: LinkTabs,
  render: (args) => (
    <div onClickCapture={stopVisit}>
      <LinkTabs {...args} />
    </div>
  ),
  args: {
    items: views,
    ariaLabel: LABEL,
  },
} satisfies Meta<typeof LinkTabs>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The keyboard path: Tab goes from link to link, in the order of the row, and the arrow keys do nothing
 */
export const Default: Story = {
  play: async ({ canvasElement }) => {
    const nav = within(canvasElement).getByRole('navigation', { name: LABEL });
    const tabs = within(nav);
    const upcoming = tabs.getByRole('link', { name: 'Upcoming' });
    const past = tabs.getByRole('link', { name: 'Past' });
    await expect(upcoming).toHaveAttribute('href', '/communities/atlas-garden/events');
    await expect(upcoming).toHaveAttribute('aria-current', 'page');
    await expect(past).toHaveAttribute('href', '/communities/atlas-garden/events?view=past');
    await expect(past).not.toHaveAttribute('aria-current');
    // The marker of the open view is a line and a heavier text, not a color alone
    await expect(upcoming).toHaveClass('border-b-2', 'font-bold');
    await expect(past).not.toHaveClass('border-b-2', 'font-bold');
    // Links in a list, not the tabs of ARIA
    await expect(tabs.getAllByRole('listitem')).toHaveLength(2);
    await expect(nav.querySelector('[role="tablist"], [role="tab"]')).toBeNull();

    await userEvent.tab();
    await expect(upcoming).toHaveFocus();
    await userEvent.keyboard('{ArrowRight}');
    await expect(upcoming).toHaveFocus();
    await userEvent.tab();
    await expect(past).toHaveFocus();
    const address = canvasElement.ownerDocument.location.href;
    await userEvent.keyboard('{Enter}');
    await expect(canvasElement.ownerDocument.location.href).toBe(address);
  },
};

export const SecondViewOpen: Story = {
  args: {
    items: [
      { label: 'Upcoming', href: '/communities/atlas-garden/events' },
      { label: 'Past', href: '/communities/atlas-garden/events?view=past', current: true },
    ],
  },
  play: async ({ canvasElement }) => {
    const tabs = within(within(canvasElement).getByRole('navigation', { name: LABEL }));
    await expect(tabs.getByRole('link', { name: 'Past' })).toHaveAttribute('aria-current', 'page');
    await expect(tabs.getByRole('link', { name: 'Upcoming' })).not.toHaveAttribute('aria-current');
  },
};

/**
 * The open page is none of the views (a page below them): no link is marked
 */
export const NoViewOpen: Story = {
  args: {
    items: views.map(({ label, href }) => ({ label, href })),
  },
  play: async ({ canvasElement }) => {
    const nav = within(canvasElement).getByRole('navigation', { name: LABEL });
    await expect(nav.querySelector('[aria-current]')).toBeNull();
  },
};

const manyViews: readonly LinkTabItem[] = [
  { label: 'Upcoming events', href: '/events' },
  { label: 'Events of this week', href: '/events?view=week', current: true },
  { label: 'Events that you host', href: '/events?view=host' },
  { label: 'Events that you go to', href: '/events?view=going' },
  { label: 'Past events', href: '/events?view=past' },
  { label: 'Cancelled events', href: '/events?view=cancelled' },
];

export const ManyViews: Story = {
  args: { items: manyViews },
};

/**
 * On a phone, the links wrap to more lines: the page does not scroll sideways, and every link stays in view
 */
export const ManyViewsOnPhone: Story = {
  args: { items: manyViews },
  globals: { viewport: { value: 'phone', isRotated: false } },
  play: async ({ canvasElement }) => {
    const nav = within(canvasElement).getByRole('navigation', { name: LABEL });
    await expect(within(nav).getAllByRole('link')).toHaveLength(manyViews.length);
    await expect(within(nav).getByRole('list')).toHaveClass('flex-wrap');
    // 44 px high on a touch screen
    await expect(within(nav).getByRole('link', { name: 'Past events' })).toHaveClass('touch-target');
  },
};
