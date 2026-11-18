import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { Divider } from './Divider';

/**
 * A thin line between two groups of content that belong to the same section (the parts of a form, the groups of a
 * menu). Between two sections, use their headings instead. The space above and below is 1 rem; `className` changes it.
 *
 * Accessibility: an `<hr>`, a separator for screen readers. It takes no focus.
 */
const meta = {
  component: Divider,
  render: (args) => (
    <div className="max-w-xl">
      <p>Sow the seeds in March, inside.</p>
      <Divider {...args} />
      <p>Plant the seedlings out after the last frost.</p>
    </div>
  ),
} satisfies Meta<typeof Divider>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('separator')).toBeInTheDocument();
  },
};

export const MoreSpace: Story = {
  args: { className: 'my-8' },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('separator')).toHaveClass('my-8');
  },
};

export const NoSpace: Story = {
  args: { className: 'my-0' },
};
