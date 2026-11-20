import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { Card } from './Card';
import { CardGroup } from './CardGroup';

interface Community {
  name: string;
  description: string;
}

const communities: Community[] = [
  { name: 'Seed savers', description: 'We swap the seeds of old vegetables and write down how each one grows.' },
  { name: 'Compost club', description: 'Three bins, the right mix of green and brown, and a turn every two weeks.' },
  { name: 'Balcony gardens', description: 'Herbs, tomatoes and flowers in pots, for the members without a garden.' },
];

/**
 * One item of the grid, as the dashboard writes it: an `li` that holds one card
 */
function CommunityItem({ name, description }: Community) {
  return (
    <li>
      <Card as="article" className="h-full p-5">
        <h3 className="text-lg leading-tight font-extrabold">{name}</h3>
        <p className="mt-2 text-sm text-muted">{description}</p>
      </Card>
    </li>
  );
}

/**
 * The grid of a list of cards (the communities of the dashboard): one column on a phone, then as many columns of
 * 20 rem or more as the width holds, with the same gap in both directions. `className` adds the space around the grid.
 *
 * Not a `Card`, and not for a list of rows: a list of posts or of members is a plain list.
 *
 * Accessibility: a `ul`, a list for screen readers. Each child must be an `li` that
 * holds one card: anything else makes the list invalid. The grid takes no focus; the order of the focus is the order
 * of the cards in the code.
 */
const meta = {
  component: CardGroup,
  args: {
    children: communities.map((community) => <CommunityItem key={community.name} {...community} />),
  },
} satisfies Meta<typeof CardGroup>;

export default meta;
type Story = StoryObj<typeof meta>;

export const ThreeCards: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const list = canvas.getByRole('list');
    await expect(list.tagName).toBe('UL');
    await expect(list).toBe(canvasElement.querySelector('ul'));
    const items = within(list).getAllByRole('listitem');
    await expect(items).toHaveLength(3);
    // Each child of the list is a list item that holds one card
    for (const child of Array.from(list.children)) {
      await expect(child.tagName).toBe('LI');
      await expect(within(child as HTMLElement).getAllByRole('article')).toHaveLength(1);
    }
  },
};

export const OneCard: Story = {
  args: {
    children: <CommunityItem name="Seed savers" description="We swap the seeds of old vegetables and write down how each one grows." />,
    // The space above the grid, as the dashboard gives it
    className: 'mt-4',
  },
  play: async ({ canvasElement }) => {
    const list = within(canvasElement).getByRole('list');
    await expect(within(list).getAllByRole('listitem')).toHaveLength(1);
    await expect(list).toHaveClass('mt-4');
    await expect(list).toHaveClass('grid');
  },
};

/**
 * On a phone the grid has one column: each card takes the width of the screen, and the page does not scroll sideways
 */
export const ThreeCardsOnPhone: Story = {
  globals: { viewport: { value: 'phone', isRotated: false } },
  play: async ({ canvasElement }) => {
    const list = within(canvasElement).getByRole('list');
    await expect(list).toHaveClass('grid-cols-1');
    await expect(within(list).getAllByRole('listitem')).toHaveLength(3);
  },
};
