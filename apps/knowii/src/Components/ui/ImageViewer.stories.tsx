import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fireEvent, fn, userEvent, waitFor, within } from 'storybook/test';
import { Button } from './Button';
import type { ImageViewerProps, ViewerImage } from './ImageViewer';
import { ImageViewer } from './ImageViewer';

const TRIGGER_NAME = 'Open the pictures of the garden';

/**
 * A picture in the story itself (a data URL): the story loads nothing from the network
 */
function picture(sky: string, ground: string): string {
  return (
    'data:image/svg+xml,' +
    encodeURIComponent(
      `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 400"><rect width="640" height="400" fill="${sky}"/>` +
        `<rect y="260" width="640" height="140" fill="${ground}"/><circle cx="520" cy="90" r="40" fill="#f3d36b"/></svg>`,
    )
  );
}

const images: ViewerImage[] = [
  { src: picture('#a9d3ea', '#4a8c3c'), alt: 'The vegetable beds in the spring, with young lettuces' },
  { src: picture('#f0be8c', '#3c6432'), alt: 'The tomatoes on their stakes at the end of the summer' },
  { src: picture('#c8c8d2', '#6e5a3c'), alt: 'The beds under a layer of leaves for the winter' },
];

/**
 * The button that opens the viewer, and the viewer with its state. The story args set the state at the start.
 */
function ViewerWithTrigger({ open: openAtStart, index: indexAtStart, onIndexChange, onClose, ...args }: ImageViewerProps) {
  const [open, setOpen] = useState(openAtStart);
  const [index, setIndex] = useState(indexAtStart);
  return (
    <>
      <Button onClick={() => setOpen(true)}>{TRIGGER_NAME}</Button>
      <ImageViewer
        {...args}
        open={open}
        index={index}
        onIndexChange={(next) => {
          setIndex(next);
          onIndexChange(next);
        }}
        onClose={() => {
          setOpen(false);
          onClose();
        }}
      />
    </>
  );
}

/**
 * The viewer of the images of a gallery (#1207): one image on the whole screen, with its alternative text as the
 * caption, its position ("2 / 3") and a link to the original file. `MarkdownContent` opens it on a click on an image of
 * a gallery; use it for a set of images that a member looks at one by one. The zoom of the browser works: there is no
 * zoom of its own.
 *
 * Accessibility: a modal dialog named by the position ("Image 2 of 3"), which a live region reads when the image
 * changes. The focus goes to the close button, stays inside, and goes back to the element that opened the viewer.
 * The Left and Right arrows, Home and End move between the images (after the last comes the first), and so do the
 * buttons and a swipe. Escape closes. The caption is hidden from screen readers: they read the alternative text on the
 * image.
 */
const meta = {
  component: ImageViewer,
  render: (args) => <ViewerWithTrigger {...args} />,
  args: {
    open: true,
    images,
    index: 1,
    onIndexChange: fn(),
    onClose: fn(),
  },
  parameters: {
    // Each story of the docs page renders in its own frame: the viewer covers the whole frame
    docs: { story: { inline: false, iframeHeight: '480px' } },
  },
} satisfies Meta<typeof ImageViewer>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The keyboard path: Enter opens the viewer, the focus goes to the close button, the arrows, Home and End move,
 * Escape closes, and the focus goes back to the button
 */
export const KeyboardPath: Story = {
  args: { open: false, index: 0 },
  play: async ({ args, canvasElement, step }) => {
    const trigger = within(canvasElement).getByRole('button', { name: TRIGGER_NAME });
    // The viewer renders outside the canvas (a portal on the body)
    const page = within(canvasElement.ownerDocument.body);

    await step('Enter opens the viewer on the first image, with the focus on the close button', async () => {
      await userEvent.tab();
      await userEvent.keyboard('{Enter}');
      const viewer = await page.findByRole('dialog', { name: 'Image 1 of 3' });
      await waitFor(() => expect(within(viewer).getByRole('button', { name: 'Close the viewer' })).toHaveFocus());
    });

    const viewer = page.getByRole('dialog');

    await step('The arrows, Home and End move, and after the last image comes the first', async () => {
      await userEvent.keyboard('{ArrowRight}');
      await expect(viewer).toHaveAccessibleName('Image 2 of 3');
      await userEvent.keyboard('{End}');
      await expect(viewer).toHaveAccessibleName('Image 3 of 3');
      await userEvent.keyboard('{ArrowRight}');
      await expect(viewer).toHaveAccessibleName('Image 1 of 3');
      await userEvent.keyboard('{ArrowLeft}');
      await expect(viewer).toHaveAccessibleName('Image 3 of 3');
      await userEvent.keyboard('{Home}');
      await expect(viewer).toHaveAccessibleName('Image 1 of 3');
      await expect(args.onIndexChange).toHaveBeenLastCalledWith(0);
    });

    await step('Escape closes the viewer, and the focus goes back to the button', async () => {
      await userEvent.keyboard('{Escape}');
      await waitFor(() => expect(page.queryByRole('dialog')).not.toBeInTheDocument());
      await expect(args.onClose).toHaveBeenCalledOnce();
      await waitFor(() => expect(trigger).toHaveFocus());
    });
  },
};

/**
 * The second image: its alternative text, its caption and the link to the original file in a new tab
 */
export const Open: Story = {
  play: async ({ canvasElement }) => {
    const viewer = await within(canvasElement.ownerDocument.body).findByRole('dialog', { name: 'Image 2 of 3' });
    const second = images[1];
    if (!second) {
      throw new TypeError('Three images expected');
    }
    await expect(within(viewer).getByRole('img', { name: second.alt })).toHaveAttribute('src', second.src);
    const original = within(viewer).getByRole('link', { name: /^Open the original/ });
    await expect(original).toHaveAttribute('href', second.src);
    await expect(original).toHaveAttribute('target', '_blank');
    await expect(original).toHaveAttribute('rel', 'noopener noreferrer');
  },
};

/**
 * "Previous image" and "Next image" move too, and go round at the ends
 */
export const Buttons: Story = {
  args: { index: 0 },
  play: async ({ canvasElement }) => {
    const viewer = await within(canvasElement.ownerDocument.body).findByRole('dialog', { name: 'Image 1 of 3' });
    await userEvent.click(within(viewer).getByRole('button', { name: 'Previous image' }));
    await expect(viewer).toHaveAccessibleName('Image 3 of 3');
    await userEvent.click(within(viewer).getByRole('button', { name: 'Next image' }));
    await expect(viewer).toHaveAccessibleName('Image 1 of 3');
  },
};

/**
 * A swipe to the left shows the next image, to the right the previous one; a short move is not a swipe
 */
export const Swipe: Story = {
  play: async ({ canvasElement }) => {
    const viewer = await within(canvasElement.ownerDocument.body).findByRole('dialog', { name: 'Image 2 of 3' });
    const second = images[1];
    if (!second) {
      throw new TypeError('Three images expected');
    }
    // The area around the image and the buttons takes the pointer
    const area = within(viewer).getByRole('img', { name: second.alt }).parentElement?.parentElement;
    if (!area) {
      throw new TypeError('No area around the image');
    }
    fireEvent.pointerDown(area, { clientX: 300 });
    fireEvent.pointerUp(area, { clientX: 180 });
    await expect(viewer).toHaveAccessibleName('Image 3 of 3');
    fireEvent.pointerDown(area, { clientX: 100 });
    fireEvent.pointerUp(area, { clientX: 260 });
    await expect(viewer).toHaveAccessibleName('Image 2 of 3');
    fireEvent.pointerDown(area, { clientX: 100 });
    fireEvent.pointerUp(area, { clientX: 120 });
    await expect(viewer).toHaveAccessibleName('Image 2 of 3');
  },
};

/**
 * One image: no previous and next, and the arrows do nothing
 */
export const OneImage: Story = {
  args: { images: images.slice(0, 1), index: 0 },
  play: async ({ args, canvasElement }) => {
    const viewer = await within(canvasElement.ownerDocument.body).findByRole('dialog', { name: 'Image 1 of 1' });
    await expect(within(viewer).queryByRole('button', { name: 'Next image' })).toBeNull();
    await expect(within(viewer).queryByRole('button', { name: 'Previous image' })).toBeNull();
    await userEvent.keyboard('{ArrowRight}');
    await expect(viewer).toHaveAccessibleName('Image 1 of 1');
    await expect(args.onIndexChange).not.toHaveBeenCalled();
  },
};

export const OnPhone: Story = {
  globals: { viewport: { value: 'phone', isRotated: false } },
};

/**
 * A long alternative text: the caption wraps under the image
 */
export const LongCaption: Story = {
  args: {
    images: images.map((image) => ({
      ...image,
      alt: `${image.alt}. On the left, the path of bricks to the compost; on the right, the shed with the tools and the barrels of rain water.`,
    })),
  },
};
