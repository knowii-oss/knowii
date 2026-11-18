import type { KeyboardEvent } from 'react';
import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fireEvent, fn, userEvent, waitFor, within } from 'storybook/test';
import { ACCEPTED_IMAGE_TYPES, MAX_UPLOAD_SIZE_BYTES } from '@knowii/common';
import type { ImageUploader, MarkdownEditorProps, MentionSource, MentionSuggestion } from './MarkdownEditor';
import { MarkdownEditor } from './MarkdownEditor';

const LABEL = 'Your post';
const HINT_ID = 'post-hint';
const ERROR_ID = 'post-error';
const ERROR_TEXT = 'Write the text of the post.';

// jsdom has no URL.createObjectURL and no URL.revokeObjectURL, which the editor calls for the previews of the images:
// the story gives stand-ins (a picture in a data URL) where they are missing, and gives the state of the machine back
// after the story. In a browser, the real ones show the chosen file.
const MACHINE_CREATE_OBJECT_URL = Object.getOwnPropertyDescriptor(URL, 'createObjectURL');
const MACHINE_REVOKE_OBJECT_URL = Object.getOwnPropertyDescriptor(URL, 'revokeObjectURL');
const PREVIEW_STAND_IN =
  'data:image/svg+xml,' +
  encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 100"><rect width="160" height="100" fill="#a9d3ea"/>' +
      '<rect y="65" width="160" height="35" fill="#4a8c3c"/></svg>',
  );

// Two small PNG pictures (48 x 32 px: a sky over a bed), made in the story: nothing loads from the network
const SKY_PNG =
  'iVBORw0KGgoAAAANSUhEUgAAADAAAAAgCAIAAADbtmxLAAAAO0lEQVR42u3OMQ0AIAwAsAnjRh1KkDMbmJgGvi1pUgGNm6+VEBISEhISEhISEhoW2me1IiQkJCQk9KkA2IhiZnIPGa0AAAAASUVORK5CYII=';
const DUSK_PNG =
  'iVBORw0KGgoAAAANSUhEUgAAADAAAAAgCAIAAADbtmxLAAAAO0lEQVR42u3OMQ0AIAwAsGniRg5y0DRNSJgGvi1pUgGNl7eVEBISEhISEhISEhoW2me1IiQkJCQk9KkAGtcyl92ezqUAAAAASUVORK5CYII=';

function pngFile(name: string, base64: string): File {
  return new File([Uint8Array.from(atob(base64), (character) => character.charCodeAt(0))], name, { type: 'image/png' });
}

function escapeHtml(text: string): string {
  return text.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
}

/**
 * A local stand-in of the renderer of the server: paragraphs, bold and italic, with the HTML escaped. The application
 * calls the server, so that the preview is the published content.
 */
function fakeRender(markdown: string): string {
  return markdown
    .split(/\n{2,}/)
    .filter((paragraph) => paragraph.trim() !== '')
    .map(
      (paragraph) =>
        `<p>${escapeHtml(paragraph)
          .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
          .replace(/_(.+?)_/g, '<em>$1</em>')}</p>`,
    )
    .join('');
}

const renderPreview: MarkdownEditorProps['renderPreview'] = fn((markdown: string) => Promise.resolve(fakeRender(markdown)));

/**
 * A local stand-in of the upload: the image is "stored" at once, under the name of the file
 */
function uploader(upload: ImageUploader['upload']): ImageUploader {
  return { upload, maxSizeBytes: MAX_UPLOAD_SIZE_BYTES, acceptedTypes: ACCEPTED_IMAGE_TYPES };
}

const storeImage: ImageUploader['upload'] = (file) => Promise.resolve({ url: `/storage/uploads/${file.name.replace(/\.png$/, '.webp')}` });

// The files that failed once in the story of a failed upload: the second try works
const failedOnce = new Set<string>();

const storeImageButDuskOnce: ImageUploader['upload'] = (file, alt) => {
  if (file.name === 'dusk.png' && !failedOnce.has(file.name)) {
    failedOnce.add(file.name);
    return Promise.resolve({ error: 'The image could not be read.' });
  }
  return storeImage(file, alt);
};

// The members of the community of the stories: neutral names
const MEMBERS: MentionSuggestion[] = [
  { username: 'rose', name: 'Rose Gardener' },
  { username: 'rosemary', name: 'Rosemary Field' },
  { username: 'basil', name: 'Basil Grower' },
];

/**
 * A local stand-in of the search of the members: the usernames that start with the words typed after "@"
 */
const members: MentionSource = {
  search: fn((query: string) => Promise.resolve(MEMBERS.filter((member) => member.username.startsWith(query.toLowerCase())))),
};

interface StoryArgs extends MarkdownEditorProps {
  /**
   * Show the error text under the editor, linked with aria-describedby
   */
  withError?: boolean;
}

/**
 * The editor in a form, with its label, its hint and its value. With `withError`, the error text shows under it.
 */
function EditorInAForm({ value: valueAtStart, onChange, withError = false, ...args }: StoryArgs) {
  const [value, setValue] = useState(valueAtStart);
  return (
    <div className="flex max-w-3xl flex-col gap-1">
      <label htmlFor={args.id} className="text-sm font-bold">
        {LABEL}
      </label>
      <MarkdownEditor
        {...args}
        value={value}
        onChange={(next) => {
          setValue(next);
          onChange(next);
        }}
        invalid={withError}
        aria-describedby={withError ? `${HINT_ID} ${ERROR_ID}` : HINT_ID}
      />
      <p id={HINT_ID} className="text-sm text-muted">
        The members of Vegetables read it in the space.
      </p>
      {withError && (
        <p id={ERROR_ID} className="text-sm text-danger-text">
          {ERROR_TEXT}
        </p>
      )}
    </div>
  );
}

function textField(canvasElement: HTMLElement): HTMLTextAreaElement {
  const field = canvasElement.querySelector('textarea');
  if (!field) {
    throw new TypeError('No text field');
  }
  return field;
}

function fileInput(canvasElement: HTMLElement): HTMLInputElement {
  const input = canvasElement.querySelector<HTMLInputElement>('input[type=file]');
  if (!input) {
    throw new TypeError('No file input');
  }
  return input;
}

/**
 * The editor of the Markdown of a post, a comment or a message: a Write tab (the text, a toolbar and the shortcuts
 * Ctrl+B, Ctrl+I, Ctrl+K and Ctrl+E) and a Preview tab (the renderer of the server). With `mentions`, "@" suggests
 * members; with `images`, the toolbar, a paste and a drop add images with their descriptions; a video address goes alone
 * on its line. For a short text without formatting, use a `Textarea`.
 *
 * In these stories the preview, the upload and the search of the members are local stand-ins.
 *
 * Accessibility: the tabs follow the tabs pattern (the arrows, Home and End move between them, and the tab of the open
 * panel is the only tab stop). The toolbar follows the toolbar pattern: one tab stop, the arrows, Home and End move
 * between its buttons, and the tab stop stays on the last button used. The text field takes the label of the form,
 * `aria-describedby` and `aria-invalid`. With mentions it stays a text field, with `aria-autocomplete="list"`,
 * `aria-controls` while the list is open and `aria-activedescendant` on the active member: the arrows move in the list,
 * Enter or Tab inserts, Escape closes the list (not the dialog around the editor). The panels of the images and of the
 * video take the focus when they open, close with Escape, and give the focus back to the text; their errors are in the
 * description of their field. A preview that fails is announced (`role="status"`).
 */
const meta = {
  component: MarkdownEditor,
  render: (args) => <EditorInAForm {...args} />,
  args: {
    id: 'post-body',
    value: 'Sow the **tomatoes** six weeks before the last frost.',
    onChange: fn(),
    renderPreview,
    maxLength: 10000,
    placeholder: 'What did you grow this week?',
  },
  beforeEach: () => {
    if (MACHINE_CREATE_OBJECT_URL === undefined) {
      Object.defineProperty(URL, 'createObjectURL', { value: () => PREVIEW_STAND_IN, configurable: true, writable: true });
    }
    if (MACHINE_REVOKE_OBJECT_URL === undefined) {
      Object.defineProperty(URL, 'revokeObjectURL', { value: () => undefined, configurable: true, writable: true });
    }
    return () => {
      if (MACHINE_CREATE_OBJECT_URL === undefined) {
        Reflect.deleteProperty(URL, 'createObjectURL');
      }
      if (MACHINE_REVOKE_OBJECT_URL === undefined) {
        Reflect.deleteProperty(URL, 'revokeObjectURL');
      }
    };
  },
} satisfies Meta<StoryArgs>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The keyboard path of the tabs: Tab reaches the Write tab, the Right arrow opens the preview, Home goes back to the
 * text. The toolbar shows only on the Write tab.
 */
export const KeyboardPath: Story = {
  play: async ({ canvasElement, step }) => {
    const canvas = within(canvasElement);
    const write = canvas.getByRole('tab', { name: 'Write' });
    const preview = canvas.getByRole('tab', { name: 'Preview' });
    await expect(canvas.getByRole('tablist', { name: 'Editor mode' })).toBeInTheDocument();
    await expect(write).toHaveAttribute('aria-selected', 'true');
    await expect(preview).toHaveAttribute('tabindex', '-1');

    await step('The Right arrow opens the preview, rendered by the server', async () => {
      await userEvent.tab();
      await expect(write).toHaveFocus();
      await userEvent.keyboard('{ArrowRight}');
      await expect(preview).toHaveFocus();
      await expect(preview).toHaveAttribute('aria-selected', 'true');
      const panel = canvas.getByRole('tabpanel', { name: 'Preview' });
      await waitFor(() => expect(panel.querySelector('strong')).toHaveTextContent('tomatoes'));
      await expect(canvas.queryByRole('toolbar')).toBeNull();
    });

    await step('Home goes back to the text and its toolbar', async () => {
      await userEvent.keyboard('{Home}');
      await expect(write).toHaveFocus();
      await expect(write).toHaveAttribute('aria-selected', 'true');
      await expect(canvas.getByRole('toolbar', { name: 'Formatting' })).toBeInTheDocument();
      await expect(canvas.getByRole('textbox', { name: LABEL })).toHaveAccessibleDescription(
        'The members of Vegetables read it in the space.',
      );
    });
  },
};

/**
 * The keyboard path of the toolbar: one tab stop. The arrows move between the buttons, Home and End go to the first and
 * the last one, and Tab leaves the toolbar. The tab stop stays on the last button used.
 */
export const ToolbarKeyboardPath: Story = {
  args: { images: uploader(fn(storeImage)) },
  play: async ({ canvasElement, step }) => {
    const canvas = within(canvasElement);
    const toolbar = within(canvas.getByRole('toolbar', { name: 'Formatting' }));
    const bold = toolbar.getByRole('button', { name: 'Bold (Ctrl+B)' });
    const italic = toolbar.getByRole('button', { name: 'Italic (Ctrl+I)' });
    const video = toolbar.getByRole('button', { name: 'Add a video' });
    const field = canvas.getByRole('textbox', { name: LABEL });

    await step('Tab reaches the first button, and the next Tab goes to the text', async () => {
      await expect(toolbar.getAllByRole('button').filter((button) => button.tabIndex === 0)).toEqual([bold]);
      canvas.getByRole('tab', { name: 'Write' }).focus();
      await userEvent.tab();
      await expect(bold).toHaveFocus();
      await userEvent.tab();
      await expect(field).toHaveFocus();
    });

    await step('The arrows, Home and End move between the buttons', async () => {
      bold.focus();
      await userEvent.keyboard('{ArrowRight}');
      await expect(italic).toHaveFocus();
      await userEvent.keyboard('{End}');
      await expect(video).toHaveFocus();
      await userEvent.keyboard('{ArrowRight}');
      await expect(bold).toHaveFocus();
      await userEvent.keyboard('{ArrowLeft}');
      await expect(video).toHaveFocus();
      await userEvent.keyboard('{Home}');
      await expect(bold).toHaveFocus();
      await userEvent.keyboard('{ArrowRight}');
    });

    await step('The tab stop stays on the last button used', async () => {
      await userEvent.tab();
      await expect(field).toHaveFocus();
      await userEvent.tab({ shift: true });
      await expect(italic).toHaveFocus();
      await expect(bold).toHaveAttribute('tabindex', '-1');
    });
  },
};

/**
 * A shortcut and a button of the toolbar format the selected words, and the selection stays on them
 */
export const Formatting: Story = {
  args: { value: 'Water the beans' },
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    const field = textField(canvasElement);
    field.focus();
    field.setSelectionRange(10, 15);
    await userEvent.keyboard('{Control>}b{/Control}');
    await expect(field).toHaveValue('Water the **beans**');
    await expect(args.onChange).toHaveBeenLastCalledWith('Water the **beans**');

    await userEvent.click(canvas.getByRole('button', { name: 'Italic (Ctrl+I)' }));
    await expect(field).toHaveValue('Water the **_beans_**');
    await waitFor(() => expect(field).toHaveFocus());
  },
};

export const Empty: Story = {
  args: { value: '' },
  play: async ({ canvasElement }) => {
    await expect(textField(canvasElement)).toHaveAttribute('placeholder', 'What did you grow this week?');
    await userEvent.click(within(canvasElement).getByRole('tab', { name: 'Preview' }));
    await expect(await within(canvasElement).findByText('Nothing to preview yet.')).toBeInTheDocument();
  },
};

/**
 * The preview waits for the server
 */
export const PreviewLoading: Story = {
  args: { renderPreview: () => new Promise<string | null>(() => undefined) },
  play: async ({ canvasElement }) => {
    await userEvent.click(within(canvasElement).getByRole('tab', { name: 'Preview' }));
    const panel = within(within(canvasElement).getByRole('tabpanel', { name: 'Preview' }));
    const [spinner, failure] = panel.getAllByRole('status');
    await expect(spinner).toHaveTextContent('Loading the preview');
    // The region that announces a failure is in the page, empty, before the answer of the server
    await expect(failure).toBeEmptyDOMElement();
  },
};

/**
 * The server did not render the preview: a status says it, so a screen reader announces it
 */
export const PreviewFails: Story = {
  args: { renderPreview: () => Promise.resolve(null) },
  play: async ({ canvasElement }) => {
    await userEvent.click(within(canvasElement).getByRole('tab', { name: 'Preview' }));
    const message = await within(canvasElement).findByText('The preview is not available. Try again in a moment.');
    await expect(message).toHaveAttribute('role', 'status');
  },
};

/**
 * The text is refused: the field gets `aria-invalid`, and the error text is in its description
 */
export const WithError: Story = {
  args: { value: '', withError: true },
  play: async ({ canvasElement }) => {
    const field = within(canvasElement).getByRole('textbox', { name: LABEL });
    await expect(field).toHaveAttribute('aria-invalid', 'true');
    await expect(field).toHaveAccessibleDescription(`The members of Vegetables read it in the space. ${ERROR_TEXT}`);
  },
};

export const Disabled: Story = {
  args: { disabled: true },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('textbox', { name: LABEL })).toBeDisabled();
    await expect(canvas.getByRole('button', { name: 'Bold (Ctrl+B)' })).toBeDisabled();
    await expect(canvas.getByRole('button', { name: 'Add a video' })).toBeDisabled();
  },
};

/**
 * The keyboard path of an image: the description gets the focus, "Insert" without a description refuses, Enter in the
 * description uploads the image and writes it in the text, and the focus goes back to the text
 */
export const AddAnImage: Story = {
  args: { value: 'The beds in April:', images: uploader(fn(storeImage)) },
  play: async ({ args, canvasElement, step }) => {
    const canvas = within(canvasElement);
    const field = textField(canvasElement);
    const file = pngFile('beds-in-april.png', SKY_PNG);
    // The image goes where the caret is: at the end of the text
    field.setSelectionRange(field.value.length, field.value.length);

    await step('The chosen image waits for its description, which gets the focus', async () => {
      await userEvent.upload(fileInput(canvasElement), file);
      await expect(canvas.getByRole('group', { name: 'Add an image' })).toBeInTheDocument();
      await waitFor(() => expect(canvas.getByRole('textbox', { name: 'Image description (alt text)' })).toHaveFocus());
    });

    await step('"Insert" without a description refuses and uploads nothing', async () => {
      await userEvent.click(canvas.getByRole('button', { name: 'Insert the image' }));
      await expect(await canvas.findByRole('alert')).toHaveTextContent('Describe the image for people who cannot see it.');
      await expect(args.images?.upload).not.toHaveBeenCalled();
    });

    await step('Enter in the description uploads the image and writes it in the text', async () => {
      const description = canvas.getByRole('textbox', { name: 'Image description (alt text)' });
      await waitFor(() => expect(description).toHaveFocus());
      await userEvent.type(description, 'Young lettuces in the beds{Enter}');
      await waitFor(() =>
        expect(field).toHaveValue('The beds in April:\n![Young lettuces in the beds](/storage/uploads/beds-in-april.webp)\n'),
      );
      await expect(args.images?.upload).toHaveBeenCalledWith(file, 'Young lettuces in the beds');
      await expect(canvas.queryByRole('group', { name: 'Add an image' })).toBeNull();
      await waitFor(() => expect(field).toHaveFocus());
    });
  },
};

/**
 * Several images at a time: each one has its description, and they go in the text one per line (a gallery)
 */
export const SeveralImages: Story = {
  args: { value: '', images: uploader(fn(storeImage)) },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.upload(fileInput(canvasElement), [pngFile('sky.png', SKY_PNG), pngFile('dusk.png', DUSK_PNG)]);
    const first = canvas.getByRole('textbox', { name: 'Description of image 1 of 2 (alt text)' });
    await waitFor(() => expect(first).toHaveFocus());
    await userEvent.type(first, 'The beds in the morning');
    await userEvent.type(canvas.getByRole('textbox', { name: 'Description of image 2 of 2 (alt text)' }), 'The beds at dusk');
    await userEvent.click(canvas.getByRole('button', { name: 'Insert the 2 images' }));
    await waitFor(() =>
      expect(textField(canvasElement)).toHaveValue(
        '![The beds in the morning](/storage/uploads/sky.webp)\n![The beds at dusk](/storage/uploads/dusk.webp)\n',
      ),
    );
  },
};

/**
 * The images wait for their descriptions, as they show after a choice of files
 */
export const ImagesWaiting: Story = {
  args: { value: '', images: uploader(fn(storeImage)) },
  play: async ({ canvasElement }) => {
    await userEvent.upload(fileInput(canvasElement), [pngFile('sky.png', SKY_PNG), pngFile('dusk.png', DUSK_PNG)]);
    await expect(within(canvasElement).getByRole('group', { name: 'Add 2 images' })).toBeInTheDocument();
  },
};

/**
 * An upload fails: nothing goes in the text, the error shows at its image, and a second "Insert" uploads only that one
 */
export const UploadFails: Story = {
  args: { value: '', images: uploader(fn(storeImageButDuskOnce)) },
  beforeEach: () => {
    failedOnce.clear();
  },
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.upload(fileInput(canvasElement), [pngFile('sky.png', SKY_PNG), pngFile('dusk.png', DUSK_PNG)]);
    const first = canvas.getByRole('textbox', { name: 'Description of image 1 of 2 (alt text)' });
    await waitFor(() => expect(first).toHaveFocus());
    await userEvent.type(first, 'The beds in the morning');
    await userEvent.type(canvas.getByRole('textbox', { name: 'Description of image 2 of 2 (alt text)' }), 'The beds at dusk');
    await userEvent.click(canvas.getByRole('button', { name: 'Insert the 2 images' }));

    await expect(await canvas.findByRole('alert')).toHaveTextContent('The image could not be read.');
    await expect(canvas.getByText('Uploaded')).toBeInTheDocument();
    await expect(textField(canvasElement)).toHaveValue('');

    await userEvent.click(canvas.getByRole('button', { name: 'Insert the 2 images' }));
    await waitFor(() =>
      expect(textField(canvasElement)).toHaveValue(
        '![The beds in the morning](/storage/uploads/sky.webp)\n![The beds at dusk](/storage/uploads/dusk.webp)\n',
      ),
    );
    await expect(args.images?.upload).toHaveBeenCalledTimes(3);
  },
};

/**
 * A dropped file that is not an image is refused with a message, and the browser does not open it in place of the page
 */
export const RefusedFile: Story = {
  args: { value: '', images: uploader(fn(storeImage)) },
  play: async ({ canvasElement }) => {
    const notes = new File(['notes'], 'seed-list.pdf', { type: 'application/pdf' });
    await expect(fireEvent.drop(textField(canvasElement), { dataTransfer: { files: [notes], types: ['Files'] } })).toBe(false);
    await expect(within(canvasElement).getByRole('alert')).toHaveTextContent('Choose a JPEG, PNG, WebP or GIF image.');
    await expect(within(canvasElement).queryByRole('group', { name: 'Add an image' })).toBeNull();
  },
};

/**
 * The keyboard path of a video: the address gets the focus, a wrong address is refused, a good one goes alone on its
 * line, and the focus goes back to the text. Escape cancels.
 */
export const AddAVideo: Story = {
  args: { value: 'How to prune the tomatoes:' },
  play: async ({ canvasElement, step }) => {
    const canvas = within(canvasElement);
    const field = textField(canvasElement);
    const addVideo = canvas.getByRole('button', { name: 'Add a video' });
    // The video goes where the caret is: at the end of the text
    field.setSelectionRange(field.value.length, field.value.length);

    await step('Escape cancels, and the focus goes back to the text', async () => {
      await userEvent.click(addVideo);
      await expect(canvas.getByRole('textbox', { name: 'Video address (YouTube or Vimeo)' })).toHaveFocus();
      await userEvent.keyboard('{Escape}');
      await expect(canvas.queryByRole('group', { name: 'Add a video' })).toBeNull();
      await expect(field).toHaveFocus();
    });

    await step('A wrong address is refused', async () => {
      await userEvent.click(addVideo);
      const address = canvas.getByRole('textbox', { name: 'Video address (YouTube or Vimeo)' });
      await expect(address).toHaveAccessibleDescription(
        'The video plays in the text. Nothing loads from YouTube or Vimeo before a reader presses Play.',
      );
      await userEvent.type(address, 'https://example.com/pruning{Enter}');
      await expect(canvas.getByRole('alert')).toHaveTextContent('Enter the address of a YouTube or Vimeo video.');
      await expect(address).toHaveAttribute('aria-invalid', 'true');
    });

    await step('A good address goes alone on its line', async () => {
      const address = canvas.getByRole('textbox', { name: 'Video address (YouTube or Vimeo)' });
      await userEvent.clear(address);
      await userEvent.type(address, 'https://vimeo.com/76979871{Enter}');
      await expect(canvas.queryByRole('group', { name: 'Add a video' })).toBeNull();
      await expect(field).toHaveValue('How to prune the tomatoes:\n\nhttps://vimeo.com/76979871\n');
      await waitFor(() => expect(field).toHaveFocus());
    });
  },
};

/**
 * The address of a video is refused: the error shows under the field, and it is in the description of the field,
 * before the hint
 */
export const VideoAddressRefused: Story = {
  args: { value: '' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(canvas.getByRole('button', { name: 'Add a video' }));
    const address = canvas.getByRole('textbox', { name: 'Video address (YouTube or Vimeo)' });
    await userEvent.type(address, 'https://example.com/pruning{Enter}');
    await expect(canvas.getByRole('alert')).toHaveTextContent('Enter the address of a YouTube or Vimeo video.');
    await expect(address).toHaveAccessibleDescription(
      'Enter the address of a YouTube or Vimeo video. The video plays in the text. Nothing loads from YouTube or Vimeo before a reader presses Play.',
    );
  },
};

/**
 * The keyboard path of a mention: "@" and the first letters open the list of the members, the arrows move, Enter
 * inserts the active member. The text field keeps its role: it points to the list while it is open.
 */
export const Mention: Story = {
  args: { value: '', mentions: members },
  play: async ({ canvasElement, step }) => {
    const canvas = within(canvasElement);
    const field = canvas.getByRole('textbox', { name: LABEL });
    await expect(field).toHaveAttribute('aria-autocomplete', 'list');
    await expect(field).not.toHaveAttribute('aria-controls');

    await step('"@" and the first letters open the list', async () => {
      await userEvent.type(field, 'Thanks @ro');
      const listbox = await canvas.findByRole('listbox', { name: 'Members to mention' });
      await expect(within(listbox).getAllByRole('option')).toHaveLength(2);
      await expect(field).toHaveAttribute('aria-controls', listbox.id);
      const rose = within(listbox).getByRole('option', { name: /@rose Rose Gardener/ });
      await expect(rose).toHaveAttribute('aria-selected', 'true');
      await expect(field).toHaveAttribute('aria-activedescendant', rose.id);
    });

    await step('The arrows move, and Enter inserts the active member', async () => {
      const listbox = canvas.getByRole('listbox', { name: 'Members to mention' });
      await userEvent.keyboard('{ArrowDown}');
      const rosemary = within(listbox).getByRole('option', { name: /@rosemary/ });
      await expect(rosemary).toHaveAttribute('aria-selected', 'true');
      await expect(field).toHaveAttribute('aria-activedescendant', rosemary.id);
      await userEvent.keyboard('{ArrowUp}{ArrowUp}');
      await expect(field).toHaveAttribute('aria-activedescendant', rosemary.id);
      await userEvent.keyboard('{ArrowDown}');
      await userEvent.keyboard('{Enter}');
      await expect(field).toHaveValue('Thanks @rose ');
      await expect(field).toHaveFocus();
      await expect(listbox).not.toBeVisible();
      await expect(field).not.toHaveAttribute('aria-controls');
      await expect(field).not.toHaveAttribute('aria-activedescendant');
    });
  },
};

/**
 * Escape closes the list of the members and keeps the text (and a dialog around the editor stays open)
 */
export const MentionClosedWithEscape: Story = {
  args: { value: '', mentions: members },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const field = canvas.getByRole('textbox', { name: LABEL });
    await userEvent.type(field, '@ba');
    const listbox = await canvas.findByRole('listbox', { name: 'Members to mention' });
    await expect(within(listbox).getByRole('option', { name: /@basil Basil Grower/ })).toBeInTheDocument();
    await userEvent.keyboard('{Escape}');
    await expect(listbox).not.toBeVisible();
    await expect(field).toHaveValue('@ba');
    await expect(field).not.toHaveAttribute('aria-controls');
  },
};

/**
 * A search that finds no member: the list stays closed, and Enter writes a new line as usual
 */
export const MentionWithoutResult: Story = {
  args: { value: '', mentions: members },
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    const field = canvas.getByRole('textbox', { name: LABEL });
    await userEvent.type(field, '@zucchini');
    await waitFor(() => expect(args.mentions?.search).toHaveBeenCalledWith('zucchini', expect.any(AbortSignal)));
    await expect(canvas.queryByRole('listbox')).toBeNull();
    await expect(field).not.toHaveAttribute('aria-controls');
    await userEvent.keyboard('{Enter}');
    await expect(field).toHaveValue('@zucchini\n');
  },
};

/**
 * The keys of the field go to `onKeyDown` after the suggestions: a chat sends the message with Enter
 */
export const EnterSends: Story = {
  args: {
    value: '',
    rows: 2,
    onKeyDown: fn((event: KeyboardEvent<HTMLTextAreaElement>) => {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
      }
    }),
  },
  play: async ({ args, canvasElement }) => {
    const field = textField(canvasElement);
    await userEvent.type(field, 'The seeds are here{Enter}');
    await expect(args.onKeyDown).toHaveBeenCalled();
    await expect(field).toHaveValue('The seeds are here');
    await userEvent.type(field, '{Shift>}{Enter}{/Shift}in the shed');
    await expect(field).toHaveValue('The seeds are here\nin the shed');
  },
};

export const OnPhone: Story = {
  args: { images: uploader(fn(storeImage)) },
  globals: { viewport: { value: 'phone', isRotated: false } },
};

export const LongText: Story = {
  args: {
    value: Array.from(
      { length: 12 },
      (_, index) =>
        `Week ${index + 1}: the **tomatoes** grow, the beans climb the stakes, and the _lettuces_ wait for the rain. ` +
        'https://example.com/garden/notes/a-very-long-address-that-does-not-break-on-its-own',
    ).join('\n\n'),
    rows: 12,
  },
};
