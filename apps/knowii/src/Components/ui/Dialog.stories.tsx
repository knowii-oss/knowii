import { useId, useState } from 'react';
import { useClose } from '@headlessui/react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, waitFor, within } from 'storybook/test';
import { Button } from './Button';
import type { DialogProps } from './Dialog';
import { Dialog, DialogCloseButton } from './Dialog';
import { TextInput } from './TextInput';

const TRIGGER_NAME = 'Edit the space';

function focusedElement(document: Document): HTMLElement | null {
  return document.activeElement instanceof HTMLElement ? document.activeElement : null;
}

/**
 * The button that opens the dialog, and the dialog. The story args set the state at the start.
 */
function DialogWithTrigger({ open: openAtStart, onClose, ...args }: DialogProps) {
  const [open, setOpen] = useState(openAtStart);
  return (
    <>
      <Button onClick={() => setOpen(true)}>{TRIGGER_NAME}</Button>
      <Dialog
        {...args}
        open={open}
        onClose={() => {
          setOpen(false);
          onClose();
        }}
      />
    </>
  );
}

function NameField() {
  const id = useId();
  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={id} className="text-sm font-bold">
        Name
      </label>
      {/* The dialog gives the focus to the field with autoFocus (see Dialog) */}
      <TextInput id={id} defaultValue="General" autoFocus />
    </div>
  );
}

function FooterButtons() {
  // Closes the dialog that holds the buttons, through its onClose
  const close = useClose();
  return (
    <>
      {/* Closes the dialog like Escape: with unsaved changes, it asks first */}
      <DialogCloseButton>Cancel</DialogCloseButton>
      <Button onClick={close}>Save</Button>
    </>
  );
}

/**
 * The dialog of a form with text: it has unsaved changes once the text is not the one at the start (#1563)
 */
function DialogWithText({ open: openAtStart, onClose, ...args }: DialogProps) {
  const [open, setOpen] = useState(openAtStart);
  const id = useId();
  const [text, setText] = useState('');
  return (
    <>
      <Button onClick={() => setOpen(true)}>{TRIGGER_NAME}</Button>
      <Dialog
        {...args}
        open={open}
        onClose={() => {
          setOpen(false);
          setText('');
          onClose();
        }}
        hasUnsavedChanges={text !== ''}
      >
        <div className="flex flex-col gap-1">
          <label htmlFor={id} className="text-sm font-bold">
            Description
          </label>
          <TextInput id={id} value={text} onChange={(event) => setText(event.target.value)} autoFocus />
        </div>
      </Dialog>
    </>
  );
}

/**
 * A modal dialog for a short task that needs the full attention of the member: a form or a question. For a long task,
 * use a page. The dialog closes with Escape, a click outside the panel and the close button.
 *
 * Accessibility: the title names the dialog and the description describes it. The focus goes to the field with
 * `autoFocus` (else to the first element that takes the focus), stays inside while the dialog is open, and goes back
 * to the button that opened it.
 */
const meta = {
  component: Dialog,
  render: (args) => <DialogWithTrigger {...args} />,
  args: {
    open: true,
    onClose: fn(),
    title: 'Edit the space',
    description: 'The name shows in the navigation of the community.',
    children: <NameField />,
    footer: <FooterButtons />,
  },
  parameters: {
    // Each story of the docs page renders in its own frame: an open modal dialog covers the whole frame
    docs: { story: { inline: false, iframeHeight: '480px' } },
  },
} satisfies Meta<typeof Dialog>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The keyboard path: the dialog opens, the focus goes inside, Tab stays inside, Escape closes, and the focus goes back
 * to the button.
 */
export const KeyboardPath: Story = {
  args: { open: false },
  play: async ({ args, canvasElement, step }) => {
    const trigger = within(canvasElement).getByRole('button', { name: TRIGGER_NAME });
    // The dialog renders outside the canvas (a portal on the body)
    const page = within(canvasElement.ownerDocument.body);

    await step('Enter on the button opens the dialog', async () => {
      await userEvent.tab();
      await expect(trigger).toHaveFocus();
      await userEvent.keyboard('{Enter}');
    });

    const dialog = await page.findByRole('dialog', { name: 'Edit the space' });

    await step('The focus goes to the field with autoFocus', async () => {
      await waitFor(() => expect(within(dialog).getByRole('textbox', { name: 'Name' })).toHaveFocus());
    });

    await step('Tab and Shift+Tab keep the focus inside the dialog', async () => {
      // The close button, the field, Cancel and Save: one more Tab than that goes round once
      for (let index = 0; index < 5; index++) {
        await userEvent.tab();
        await waitFor(() => expect(dialog).toContainElement(focusedElement(canvasElement.ownerDocument)));
      }
      await userEvent.tab({ shift: true });
      await waitFor(() => expect(dialog).toContainElement(focusedElement(canvasElement.ownerDocument)));
    });

    await step('Escape closes the dialog', async () => {
      await userEvent.keyboard('{Escape}');
      await waitFor(() => expect(page.queryByRole('dialog')).not.toBeInTheDocument());
      await expect(args.onClose).toHaveBeenCalledOnce();
    });

    await step('The focus goes back to the button', async () => {
      await waitFor(() => expect(trigger).toHaveFocus());
    });
  },
};

/**
 * A form with text that is not saved (#1563): Escape, a click outside the panel, the close button and "Cancel" ask
 * "Discard your text?" first. "Keep writing" has the focus, Escape on the question means "Keep writing", and the focus
 * goes back to the field. "Discard" closes the dialog.
 */
export const AsksBeforeItLosesText: Story = {
  args: { open: false },
  render: (args) => <DialogWithText {...args} />,
  play: async ({ args, canvasElement, step }) => {
    const trigger = within(canvasElement).getByRole('button', { name: TRIGGER_NAME });
    const page = within(canvasElement.ownerDocument.body);
    await userEvent.click(trigger);
    const dialog = await page.findByRole('dialog', { name: 'Edit the space' });
    const field = within(dialog).getByRole('textbox', { name: 'Description' });

    await step('Escape with text asks first, and "Keep writing" has the focus', async () => {
      await waitFor(() => expect(field).toHaveFocus());
      await userEvent.keyboard('A space for the readers');
      await userEvent.keyboard('{Escape}');
      const question = await page.findByRole('dialog', { name: 'Discard your text?' });
      await waitFor(() => expect(within(question).getByRole('button', { name: 'Keep writing' })).toHaveFocus());
      await expect(args.onClose).not.toHaveBeenCalled();
    });

    await step('Escape on the question keeps the text and gives the focus back to the field', async () => {
      await userEvent.keyboard('{Escape}');
      await waitFor(() => expect(page.queryByRole('dialog', { name: 'Discard your text?' })).not.toBeInTheDocument());
      await waitFor(() => expect(field).toHaveFocus());
      await expect(field).toHaveValue('A space for the readers');
    });

    await step('"Cancel" asks too, and "Discard" closes the dialog', async () => {
      await userEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));
      const question = await page.findByRole('dialog', { name: 'Discard your text?' });
      await userEvent.click(within(question).getByRole('button', { name: 'Discard' }));
      await waitFor(() => expect(page.queryByRole('dialog')).not.toBeInTheDocument());
      await expect(args.onClose).toHaveBeenCalledOnce();
    });
  },
};

/**
 * The question on top of the dialog, as it shows after Escape
 */
export const DiscardQuestion: Story = {
  args: { open: false },
  render: (args) => <DialogWithText {...args} />,
  play: async ({ canvasElement }) => {
    const page = within(canvasElement.ownerDocument.body);
    await userEvent.click(within(canvasElement).getByRole('button', { name: TRIGGER_NAME }));
    const dialog = await page.findByRole('dialog', { name: 'Edit the space' });
    await userEvent.type(within(dialog).getByRole('textbox', { name: 'Description' }), 'A space for the readers');
    await userEvent.keyboard('{Escape}');
    const question = await page.findByRole('dialog', { name: 'Discard your text?' });
    await expect(question).toHaveAccessibleDescription('You have changes that are not saved. If you close now, they are lost.');
  },
};

export const Open: Story = {
  play: async ({ canvasElement }) => {
    const dialog = await within(canvasElement.ownerDocument.body).findByRole('dialog', { name: 'Edit the space' });
    await expect(dialog).toHaveAccessibleDescription('The name shows in the navigation of the community.');
  },
};

export const Small: Story = {
  args: { size: 'sm' },
};

export const Large: Story = {
  args: { size: 'lg' },
};

export const WithoutDescription: Story = {
  args: { description: undefined },
};

export const WithoutFooter: Story = {
  args: { footer: undefined },
};

export const LongText: Story = {
  args: {
    title: 'Edit the space of the readers who write one long note every week and share it with the other members',
    description:
      'The name shows in the navigation of the community, in the notifications of the members and in the address of ' +
      'every post of the space.',
    children: (
      <div className="flex flex-col gap-4">
        <NameField />
        {Array.from({ length: 8 }, (_, index) => (
          <p key={index}>
            A long text scrolls the dialog, not the page behind it. The title, the description and the buttons stay in the same panel, and
            the panel never gets wider than the screen.
          </p>
        ))}
      </div>
    ),
  },
};
