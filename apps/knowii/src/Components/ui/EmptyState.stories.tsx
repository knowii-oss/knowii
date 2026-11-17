import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { FaRegComments } from 'react-icons/fa6';
import { Button } from './Button';
import { EmptyState } from './EmptyState';

/**
 * What a list or a page shows when it has nothing yet: what will be here, and what to do first. The action is
 * optional: show it only when the member can do it.
 *
 * Accessibility: the icon is hidden from screen readers. The title is text, not a heading: the page keeps its own
 * heading levels.
 */
const meta = {
  component: EmptyState,
  args: {
    icon: <FaRegComments />,
    title: 'No posts yet',
    description: 'The posts of the members will show here. Write the first one.',
    action: <Button>Write a post</Button>,
  },
} satisfies Meta<typeof EmptyState>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByText('No posts yet')).toBeInTheDocument();
    await expect(canvas.getByRole('button', { name: 'Write a post' })).toBeInTheDocument();
    await expect(canvasElement.querySelector('[aria-hidden="true"]')).not.toBeNull();
  },
};

export const WithEmoji: Story = {
  args: { icon: '💬' },
};

export const WithoutIcon: Story = {
  args: { icon: undefined },
};

export const WithoutAction: Story = {
  args: { action: undefined, description: 'The posts of the members will show here.' },
};

export const TitleOnly: Story = {
  args: { icon: undefined, description: undefined, action: undefined, title: 'Nothing here' },
};

export const LongText: Story = {
  args: {
    title: 'No resources in this collection yet, and no member has suggested one this month',
    description:
      'Resources are links, books, videos and courses that the members share with the community. Each resource has a ' +
      'title, a short description and tags, so that the other members find it again. Add the first one, or ask the ' +
      'members of the community to share what they read and watch.',
  },
};
