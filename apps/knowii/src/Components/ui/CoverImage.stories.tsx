import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fireEvent, waitFor, within } from 'storybook/test';
import { CoverImage } from './CoverImage';

// A picture in the story itself (a data URL): the story loads nothing from the network. It is square, so the box cuts it.
const PICTURE =
  'data:image/svg+xml,' +
  encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400"><rect width="400" height="400" fill="#2f6b3a"/>' +
      '<circle cx="200" cy="200" r="120" fill="#e8d9a8"/><rect x="190" y="200" width="20" height="200" fill="#e8d9a8"/></svg>',
  );

// A data URL that is not a picture: the browser cannot decode it, like a picture that is gone from the server
const BROKEN_PICTURE = 'data:image/png;base64,AAAA';

const ALT = 'A sunflower in the garden of the school';

/**
 * The cover picture of an item (an event), in a 16:9 box with the rounded corners of a `Card`. The picture fills the
 * box and is cut at its edges. Without an address, or when the picture cannot load, the box shows a neutral
 * placeholder of the same size, without a text. For the picture of a person, use an `Avatar`.
 *
 * Accessibility: `alt` says what the picture shows; an empty `alt` marks a decoration, which screen readers skip (the
 * title of the item is in the text next to it). The placeholder is hidden from screen readers. The box has its size
 * before the picture loads, so the page does not move.
 */
const meta = {
  component: CoverImage,
  render: (args) => (
    <div className="max-w-md">
      <CoverImage {...args} />
    </div>
  ),
  args: {
    src: PICTURE,
    alt: ALT,
  },
} satisfies Meta<typeof CoverImage>;

export default meta;
type Story = StoryObj<typeof meta>;

export const WithPicture: Story = {
  play: async ({ canvasElement }) => {
    const picture = within(canvasElement).getByRole('img', { name: ALT });
    await expect(picture).toHaveAttribute('loading', 'lazy');
    await expect(picture).toHaveClass('object-cover');
    // The box keeps the place: 16:9, with the corners of a card
    await expect(picture.parentElement).toHaveClass('aspect-video', 'rounded-xl', 'overflow-hidden');
  },
};

/**
 * An empty `alt`: the picture is a decoration and has no role for a screen reader
 */
export const Decorative: Story = {
  args: { alt: '' },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).queryByRole('img')).toBeNull();
    await expect(canvasElement.querySelector('img')).toHaveAttribute('alt', '');
  },
};

/**
 * No picture: a neutral placeholder in the same box, without a text
 */
export const WithoutPicture: Story = {
  args: { src: null },
  play: async ({ canvasElement }) => {
    await expect(canvasElement.querySelector('img')).toBeNull();
    const box = canvasElement.querySelector('.aspect-video');
    await expect(box).toHaveClass('aspect-video', 'rounded-xl');
    await expect(box).toHaveTextContent('');
    await expect(box?.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
  },
};

/**
 * The picture cannot be loaded: the placeholder shows in its place, in the same box
 */
export const WithBrokenPicture: Story = {
  args: { src: BROKEN_PICTURE },
  play: async ({ canvasElement }) => {
    const box = canvasElement.querySelector('.aspect-video');
    // A browser fails to decode the picture and sends the error event; jsdom loads no picture, so the story sends it
    const picture = box?.querySelector('img');
    if (picture) {
      fireEvent.error(picture);
    }
    await waitFor(() => expect(box?.querySelector('img')).toBeNull());
    await expect(box).toHaveClass('aspect-video');
    await expect(box).toHaveTextContent('');
    await expect(box?.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
  },
};

/**
 * The cover at the top of a page loads at once
 */
export const Eager: Story = {
  args: { loading: 'eager' },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('img', { name: ALT })).toHaveAttribute('loading', 'eager');
  },
};

/**
 * `className` sets the width of the box, which keeps its 16:9 shape
 */
export const Narrow: Story = {
  args: { className: 'w-48' },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('img', { name: ALT }).parentElement).toHaveClass('w-48', 'aspect-video');
  },
};

export const OnPhone: Story = {
  globals: { viewport: { value: 'phone', isRotated: false } },
};

export const WithoutPictureOnPhone: Story = {
  args: { src: null },
  globals: { viewport: { value: 'phone', isRotated: false } },
};
