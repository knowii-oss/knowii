import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fireEvent, waitFor, within } from 'storybook/test';
import { Avatar } from './Avatar';

// A picture in the story itself (a data URL): the story loads nothing from the network
const PICTURE =
  'data:image/svg+xml,' +
  encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80"><rect width="80" height="80" fill="#2f6b3a"/>' +
      '<circle cx="40" cy="32" r="14" fill="#e8d9a8"/><rect x="16" y="52" width="48" height="28" rx="14" fill="#e8d9a8"/></svg>',
  );

// A data URL that is not a picture: the browser cannot decode it, like a picture that is gone from the server
const BROKEN_PICTURE = 'data:image/png;base64,AAAA';

/**
 * The round picture of a person, with the initials of the name when there is no picture or when the picture cannot
 * load. Use it next to the name of the person (a post, a member list, the account menu), never alone.
 *
 * Accessibility: decorative (`aria-hidden`, the picture has an empty `alt`). The name must be in the text next to it, or
 * in the accessible name of the control that holds it.
 */
const meta = {
  component: Avatar,
  args: {
    name: 'Rose Gardener',
  },
} satisfies Meta<typeof Avatar>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Initials: Story = {
  play: async ({ canvasElement }) => {
    const avatar = canvasElement.querySelector('span');
    await expect(avatar).toHaveAttribute('aria-hidden', 'true');
    await expect(avatar).toHaveTextContent('RG');
  },
};

export const WithPicture: Story = {
  args: { imageUrl: PICTURE },
  play: async ({ canvasElement }) => {
    const avatar = canvasElement.querySelector('span');
    await expect(avatar).toHaveAttribute('aria-hidden', 'true');
    await expect(avatar?.querySelector('img')).toHaveAttribute('alt', '');
  },
};

/**
 * The picture cannot be loaded: the avatar shows the initials in its place
 */
export const WithBrokenPicture: Story = {
  args: { imageUrl: BROKEN_PICTURE },
  play: async ({ canvasElement }) => {
    const avatar = canvasElement.querySelector('span');
    // A browser fails to decode the picture and sends the error event; jsdom loads no picture, so the story sends it
    const picture = avatar?.querySelector('img');
    if (picture) {
      fireEvent.error(picture);
    }
    await waitFor(() => expect(avatar?.querySelector('img')).toBeNull());
    await expect(avatar).toHaveTextContent('RG');
  },
};

export const Small: Story = {
  args: { size: 'sm' },
};

export const Medium: Story = {
  args: { size: 'md' },
};

export const Large: Story = {
  args: { size: 'lg' },
};

export const ExtraLarge: Story = {
  args: { size: 'xl' },
};

export const ExtraLargeWithPicture: Story = {
  args: { size: 'xl', imageUrl: PICTURE },
};

export const OneWord: Story = {
  args: { name: 'basil' },
  play: async ({ canvasElement }) => {
    await expect(canvasElement.querySelector('span')).toHaveTextContent('B');
  },
};

export const WithoutName: Story = {
  args: { name: null },
  play: async ({ canvasElement }) => {
    await expect(canvasElement.querySelector('span')).toHaveTextContent('?');
  },
};

/**
 * A long name gives two initials: the first word and the last one
 */
export const LongName: Story = {
  args: { name: 'Marie Thérèse de la Roseraie du Grand Jardin Potager' },
  play: async ({ canvasElement }) => {
    await expect(canvasElement.querySelector('span')).toHaveTextContent('MP');
  },
};

/**
 * As a page shows it: the name is in the text next to the avatar
 */
export const NextToTheName: Story = {
  render: (args) => (
    <p className="flex items-center gap-3">
      <Avatar {...args} />
      <span className="font-bold">{args.name}</span>
    </p>
  ),
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByText('Rose Gardener')).toBeVisible();
  },
};
