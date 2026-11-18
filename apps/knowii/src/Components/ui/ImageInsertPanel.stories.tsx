import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, waitFor, within } from 'storybook/test';
import type { ImageInsertPanelProps, PendingImageItem } from './ImageInsertPanel';
import { imageAltId, ImageInsertPanel } from './ImageInsertPanel';

const BASE_ID = 'story';

// jsdom has no URL.revokeObjectURL, which the panel calls when an image is removed: the story gives it a stand-in that
// does nothing (the previews of the stories are data URLs), and gives the state of the machine back after the story
const MACHINE_REVOKE_OBJECT_URL = Object.getOwnPropertyDescriptor(URL, 'revokeObjectURL');

/**
 * A picture in the story itself (a data URL): the story loads nothing from the network
 */
function picture(sky: string, ground: string): string {
  return (
    'data:image/svg+xml,' +
    encodeURIComponent(
      `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 100"><rect width="160" height="100" fill="${sky}"/>` +
        `<rect y="65" width="160" height="35" fill="${ground}"/></svg>`,
    )
  );
}

function pendingImage(id: string, fileName: string, preview: string, changes: Partial<PendingImageItem> = {}): PendingImageItem {
  return {
    id,
    // The panel shows only the name of the file; the editor uploads its content
    file: new File(['png'], fileName, { type: 'image/png' }),
    previewUrl: preview,
    alt: '',
    error: null,
    status: 'waiting',
    url: null,
    ...changes,
  };
}

const LETTUCES = pendingImage('lettuces', 'lettuces-in-april.png', picture('#a9d3ea', '#4a8c3c'));
const TOMATOES = pendingImage('tomatoes', 'tomatoes-on-stakes.png', picture('#f0be8c', '#3c6432'));
const LEAVES = pendingImage('leaves', 'leaves-on-the-beds.png', picture('#c8c8d2', '#6e5a3c'));

/**
 * The panel with the list of images that the editor keeps. The story args set the list at the start.
 */
function PanelWithItems({ items: itemsAtStart, onChange, ...args }: ImageInsertPanelProps) {
  const [items, setItems] = useState(itemsAtStart);
  return (
    <div className="max-w-2xl rounded-lg border border-border bg-well pt-2">
      <ImageInsertPanel
        {...args}
        items={items}
        onChange={(next) => {
          setItems(next);
          onChange(next);
        }}
      />
    </div>
  );
}

function altField(canvasElement: HTMLElement, id: string): HTMLElement {
  const field = canvasElement.ownerDocument.getElementById(imageAltId(BASE_ID, id));
  if (!field) {
    throw new TypeError(`No description field for ${id}`);
  }
  return field;
}

/**
 * The images that a member adds to a text, before they go in (#1207). `MarkdownEditor` shows it after a choice, a paste
 * or a drop of images, and does the uploads; it is not used alone. Each image has a preview, a required description
 * (the alternative text, also the caption in the gallery), the state of its upload, and, when there are several, buttons
 * to move it and to remove it.
 *
 * Accessibility: a group named "Add an image" or "Add 3 images". Each description has its label and the hint in
 * `aria-describedby`; a missing description sets `aria-invalid`, shows its error as an alert, and puts the error in
 * `aria-describedby` before the hint. Enter in a description inserts (not while the uploads run), Escape anywhere in the panel cancels the images (not the dialog around the editor). The move buttons use
 * `aria-disabled` at the ends, so they keep the focus; a moved image keeps the focus on its button, and after a removal
 * the focus goes to the description of the next image. While the uploads run, the descriptions are read-only and the
 * buttons do nothing. The previews are decorative.
 */
const meta = {
  component: ImageInsertPanel,
  render: (args) => <PanelWithItems {...args} />,
  args: {
    baseId: BASE_ID,
    items: [LETTUCES],
    busy: false,
    message: null,
    onChange: fn(),
    onInsert: fn(),
    onCancel: fn(),
  },
  beforeEach: () => {
    if (MACHINE_REVOKE_OBJECT_URL === undefined) {
      Object.defineProperty(URL, 'revokeObjectURL', { value: () => undefined, configurable: true, writable: true });
    }
    return () => {
      if (MACHINE_REVOKE_OBJECT_URL === undefined) {
        Reflect.deleteProperty(URL, 'revokeObjectURL');
      }
    };
  },
} satisfies Meta<typeof ImageInsertPanel>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * One image: the short form. The description has its label and its hint; Enter inserts and Escape cancels.
 */
export const OneImage: Story = {
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    const group = canvas.getByRole('group', { name: 'Add an image' });
    const field = within(group).getByRole('textbox', { name: 'Image description (alt text)' });
    await expect(field).toHaveAccessibleDescription('Screen readers read this text. Describe what matters in the image.');
    await expect(within(group).queryByRole('button', { name: /Remove image/ })).toBeNull();

    await userEvent.type(field, 'Young lettuces in the bed');
    await expect(args.onChange).toHaveBeenLastCalledWith([expect.objectContaining({ alt: 'Young lettuces in the bed' })]);
    await userEvent.keyboard('{Enter}');
    await expect(args.onInsert).toHaveBeenCalledOnce();
    await userEvent.keyboard('{Escape}');
    await expect(args.onCancel).toHaveBeenCalledOnce();
  },
};

/**
 * Three images: a numbered list. A move keeps the focus on the button of the moved image; the first "Move up" is
 * `aria-disabled` and keeps the focus. After a removal, the focus goes to the description of the next image.
 */
export const SeveralImages: Story = {
  args: {
    items: [
      { ...LETTUCES, alt: 'Young lettuces in the bed' },
      { ...TOMATOES, alt: 'Tomatoes on their stakes' },
      { ...LEAVES, alt: 'Leaves on the beds for the winter' },
    ],
  },
  play: async ({ args, canvasElement, step }) => {
    const canvas = within(canvasElement);
    const group = canvas.getByRole('group', { name: 'Add 3 images' });
    await expect(within(group).getByRole('button', { name: 'Insert the 3 images' })).toBeInTheDocument();

    await step('A move keeps the focus on the button of the moved image', async () => {
      await userEvent.click(within(group).getByRole('button', { name: 'Move image 3 up' }));
      await expect(within(group).getByRole('button', { name: 'Move image 2 up' })).toHaveFocus();
      await userEvent.keyboard('{Enter}');
      const top = within(group).getByRole('button', { name: 'Move image 1 up' });
      await waitFor(() => expect(top).toHaveFocus());
      await expect(top).toHaveAttribute('aria-disabled', 'true');
      await expect(within(group).getByRole('textbox', { name: 'Description of image 1 of 3 (alt text)' })).toHaveValue(
        'Leaves on the beds for the winter',
      );
    });

    await step('The first "Move up" does nothing', async () => {
      await expect(args.onChange).toHaveBeenCalledTimes(2);
      await userEvent.keyboard('{Enter}');
      await expect(args.onChange).toHaveBeenCalledTimes(2);
    });

    await step('After a removal, the focus goes to the description of the next image', async () => {
      await userEvent.click(within(group).getByRole('button', { name: 'Remove image 1' }));
      await expect(canvas.getByRole('group', { name: 'Add 2 images' })).toBeInTheDocument();
      await waitFor(() => expect(altField(canvasElement, 'lettuces')).toHaveFocus());
    });
  },
};

/**
 * "Insert" with a description that is missing: the field is marked as invalid, its error shows, and the error is in the
 * description of the field, before the hint
 */
export const MissingDescription: Story = {
  args: {
    items: [
      { ...LETTUCES, alt: 'Young lettuces in the bed' },
      { ...TOMATOES, error: 'Describe the image for people who cannot see it.' },
    ],
  },
  play: async ({ canvasElement }) => {
    const field = within(canvasElement).getByRole('textbox', { name: 'Description of image 2 of 2 (alt text)' });
    await expect(field).toHaveAttribute('aria-invalid', 'true');
    await expect(within(canvasElement).getByRole('alert')).toHaveTextContent('Describe the image for people who cannot see it.');
    await expect(field).toHaveAccessibleDescription(
      'Describe the image for people who cannot see it. Screen readers read this text, and it is the caption of the image in the gallery. Describe what matters in the image.',
    );
    // The field without an error has only the hint
    await expect(
      within(canvasElement).getByRole('textbox', { name: 'Description of image 1 of 2 (alt text)' }),
    ).toHaveAccessibleDescription(
      'Screen readers read this text, and it is the caption of the image in the gallery. Describe what matters in the image.',
    );
  },
};

/**
 * The uploads run: the first image is uploaded, the second one uploads. The descriptions are read-only, and the buttons
 * and Escape do nothing.
 */
export const Uploading: Story = {
  args: {
    busy: true,
    items: [
      { ...LETTUCES, alt: 'Young lettuces in the bed', status: 'uploaded', url: '/storage/uploads/lettuces.webp' },
      { ...TOMATOES, alt: 'Tomatoes on their stakes', status: 'uploading' },
    ],
  },
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByText('Uploaded')).toHaveAttribute('role', 'status');
    await expect(canvas.getByText('Uploading image 2')).toBeInTheDocument();
    const field = canvas.getByRole('textbox', { name: 'Description of image 2 of 2 (alt text)' });
    await expect(field).toHaveAttribute('readonly');

    const insert = canvas.getByRole('button', { name: 'Insert the 2 images' });
    await expect(insert).toHaveAttribute('aria-busy', 'true');
    await userEvent.click(insert);
    await userEvent.click(canvas.getByRole('button', { name: 'Cancel' }));
    await userEvent.click(canvas.getByRole('button', { name: 'Remove image 1' }));
    // Enter in a description does nothing while busy, as a click on "Insert"
    field.focus();
    await userEvent.keyboard('{Enter}');
    await userEvent.keyboard('{Escape}');
    await expect(args.onInsert).not.toHaveBeenCalled();
    await expect(args.onCancel).not.toHaveBeenCalled();
    await expect(args.onChange).not.toHaveBeenCalled();
  },
};

/**
 * An upload failed: its error shows, the uploaded image keeps its state, and "Insert" tries again
 */
export const UploadFailed: Story = {
  args: {
    items: [
      { ...LETTUCES, alt: 'Young lettuces in the bed', status: 'uploaded', url: '/storage/uploads/lettuces.webp' },
      { ...TOMATOES, alt: 'Tomatoes on their stakes', status: 'failed', error: 'The image could not be read.' },
    ],
  },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('alert')).toHaveTextContent('The image could not be read.');
    await expect(canvas.getByRole('textbox', { name: 'Description of image 1 of 2 (alt text)' })).toHaveAttribute('readonly');
    await expect(canvas.getByRole('textbox', { name: 'Description of image 2 of 2 (alt text)' })).not.toHaveAttribute('readonly');
  },
};

/**
 * A problem with the whole insert: too many images for the text
 */
export const TooManyImages: Story = {
  args: {
    items: [
      { ...LETTUCES, alt: 'Young lettuces in the bed' },
      { ...TOMATOES, alt: 'Tomatoes on their stakes' },
    ],
    message: 'A text holds 20 images at most: there is room for 1 more. Remove 1.',
  },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('alert')).toHaveTextContent('there is room for 1 more');
  },
};

/**
 * On a phone, the description takes the whole line and the buttons go under it
 */
export const OnPhone: Story = {
  args: {
    items: [LETTUCES, TOMATOES, { ...LEAVES, file: new File(['png'], 'the-beds-under-a-layer-of-leaves-in-november-2026.png') }],
  },
  globals: { viewport: { value: 'phone', isRotated: false } },
};
