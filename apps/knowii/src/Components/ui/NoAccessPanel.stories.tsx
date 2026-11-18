import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { FaDoorOpen, FaLock } from 'react-icons/fa6';
import { Button, buttonClasses } from './Button';
import { NoAccessPanel } from './NoAccessPanel';

/**
 * What a page shows in place of content that the person may not read: a heading, the reason in one line, and the next
 * step (a "Join" button, a text and a link, or a link only). Use it for the whole content of a page: the title is the
 * `h1` of the page. Pure presentation: the page chooses the texts, and never names what the person may not read.
 *
 * Accessibility: the panel is a region named by its heading. The icon is hidden from screen readers. The next step comes
 * after the reason in the focus order.
 */
const meta = {
  component: NoAccessPanel,
  args: {
    icon: <FaLock />,
    title: 'You do not have access to this page',
    reason: 'This space is for the members of Atlas Garden.',
    children: (
      <Button>
        <FaDoorOpen aria-hidden="true" />
        Join
      </Button>
    ),
  },
} satisfies Meta<typeof NoAccessPanel>;

export default meta;
type Story = StoryObj<typeof meta>;

export const OpenToJoin: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('heading', { level: 1, name: 'You do not have access to this page' })).toBeInTheDocument();
    await expect(canvas.getByRole('region', { name: 'You do not have access to this page' })).toBeInTheDocument();
    await expect(canvas.getByText('This space is for the members of Atlas Garden.')).toBeInTheDocument();
    await userEvent.tab();
    await expect(canvas.getByRole('button', { name: 'Join' })).toHaveFocus();
  },
};

export const ByInvitation: Story = {
  args: {
    children: (
      <>
        <p>This community is by invitation. Ask a member for an invitation.</p>
        <a href="#community" className={buttonClasses({ variant: 'secondary' })}>
          Go to the community page
        </a>
      </>
    ),
  },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.queryByRole('button')).toBeNull();
    await userEvent.tab();
    await expect(canvas.getByRole('link', { name: 'Go to the community page' })).toHaveFocus();
  },
};

export const CommunityNotShown: Story = {
  args: {
    reason: 'This page is for the members of a community. If a member gave you this link, ask that member for an invitation.',
    children: (
      <a href="#dashboard" className={buttonClasses({ variant: 'secondary' })}>
        Go to your communities
      </a>
    ),
  },
};

export const WithoutNextStep: Story = {
  args: { children: undefined },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).queryByRole('button')).toBeNull();
  },
};

export const WithoutIcon: Story = {
  args: { icon: undefined },
  play: async ({ canvasElement }) => {
    await expect(canvasElement.querySelector('section > [aria-hidden="true"]')).toBeNull();
  },
};

export const LongName: Story = {
  args: {
    reason: 'This space is for the members of The Very Long Name Of A Community That Keeps Going On And On Without Any Break At All.',
  },
};
