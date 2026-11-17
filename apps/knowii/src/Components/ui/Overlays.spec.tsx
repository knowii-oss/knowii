import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Dialog, DialogCloseButton } from './Dialog';
import { Drawer } from './Drawer';
import { DropdownMenu, DropdownMenuItem, dropdownMenuItemClasses } from './DropdownMenu';
import { Select } from './Select';
import { Button } from './Button';

function DialogHarness() {
  const [open, setOpen] = useState(false);
  return (
    <>
      <Button onClick={() => setOpen(true)}>Open</Button>
      <Dialog
        open={open}
        onClose={() => setOpen(false)}
        title="New community"
        description="Provide the details"
        footer={<Button onClick={() => setOpen(false)}>Cancel</Button>}
      >
        <input aria-label="Name" autoFocus />
      </Dialog>
    </>
  );
}

describe('Dialog', () => {
  it('opens as a named modal dialog and focuses the autofocus field', async () => {
    render(<DialogHarness />);
    await userEvent.click(screen.getByRole('button', { name: 'Open' }));

    const dialog = await screen.findByRole('dialog', { name: 'New community' });
    expect(dialog).toHaveAccessibleDescription('Provide the details');
    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Name' })).toHaveFocus());
  });

  it('closes with Escape and gives the focus back to the trigger', async () => {
    render(<DialogHarness />);
    const trigger = screen.getByRole('button', { name: 'Open' });
    await userEvent.click(trigger);
    await screen.findByRole('dialog');

    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await waitFor(() => expect(trigger).toHaveFocus());
  });

  it('calls onAfterClose once it is closed', async () => {
    const onAfterClose = vi.fn();
    const { rerender } = render(
      <Dialog open onClose={() => undefined} onAfterClose={onAfterClose} title="Title">
        Content
      </Dialog>,
    );
    await screen.findByRole('dialog');
    expect(onAfterClose).not.toHaveBeenCalled();

    rerender(
      <Dialog open={false} onClose={() => undefined} onAfterClose={onAfterClose} title="Title">
        Content
      </Dialog>,
    );
    await waitFor(() => expect(onAfterClose).toHaveBeenCalledOnce());
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('closes with the close button', async () => {
    const onClose = vi.fn();
    render(
      <Dialog open onClose={onClose} title="Title">
        Content
      </Dialog>,
    );
    await userEvent.click(await screen.findByRole('button', { name: 'Close' }));
    expect(onClose).toHaveBeenCalled();
  });
});

/**
 * A dialog with a text field: it has unsaved changes once the text is not empty (#1563)
 */
function UnsavedHarness({ onClose, guard = true }: { onClose: () => void; guard?: boolean }) {
  const [open, setOpen] = useState(false);
  const [text, setText] = useState('');
  return (
    <>
      <Button onClick={() => setOpen(true)}>Open</Button>
      <Dialog
        open={open}
        onClose={() => {
          setOpen(false);
          onClose();
        }}
        title="New post"
        hasUnsavedChanges={guard && text !== ''}
        footer={<DialogCloseButton>Cancel</DialogCloseButton>}
      >
        <input aria-label="Title" autoFocus />
        <textarea aria-label="Text" value={text} onChange={(event) => setText(event.target.value)} />
      </Dialog>
    </>
  );
}

async function openAndType(onClose: () => void, guard = true) {
  render(<UnsavedHarness onClose={onClose} guard={guard} />);
  await userEvent.click(screen.getByRole('button', { name: 'Open' }));
  const dialog = await screen.findByRole('dialog', { name: 'New post' });
  const text = within(dialog).getByRole('textbox', { name: 'Text' });
  await userEvent.type(text, 'A long post');
  return { dialog, text };
}

describe('Dialog with unsaved changes (#1563)', () => {
  it('asks on Escape and does not close', async () => {
    const onClose = vi.fn();
    await openAndType(onClose);

    await userEvent.keyboard('{Escape}');

    const question = await screen.findByRole('dialog', { name: 'Discard your text?' });
    expect(question).toHaveAccessibleDescription('You have changes that are not saved. If you close now, they are lost.');
    await waitFor(() => expect(within(question).getByRole('button', { name: 'Keep writing' })).toHaveFocus());
    // The dialog stays, hidden from assistive technology while the question is open
    expect(screen.getByRole('dialog', { name: 'New post', hidden: true }).closest('[aria-hidden="true"]')).not.toBeNull();
    expect(onClose).not.toHaveBeenCalled();
  });

  it('keeps the dialog and gives the focus back to the form after "Keep writing"', async () => {
    const onClose = vi.fn();
    const { text } = await openAndType(onClose);
    await userEvent.keyboard('{Escape}');
    const question = await screen.findByRole('dialog', { name: 'Discard your text?' });

    await userEvent.click(within(question).getByRole('button', { name: 'Keep writing' }));

    // At once, while the question fades out: the next key goes to the text
    expect(text).toHaveFocus();
    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Discard your text?' })).not.toBeInTheDocument());
    expect(text).toHaveFocus();
    expect(text).toHaveValue('A long post');
    expect(onClose).not.toHaveBeenCalled();
  });

  it('means "Keep writing" with Escape on the question', async () => {
    const onClose = vi.fn();
    const { text } = await openAndType(onClose);
    await userEvent.keyboard('{Escape}');
    const question = await screen.findByRole('dialog', { name: 'Discard your text?' });
    await waitFor(() => expect(within(question).getByRole('button', { name: 'Keep writing' })).toHaveFocus());

    await userEvent.keyboard('{Escape}');

    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Discard your text?' })).not.toBeInTheDocument());
    await waitFor(() => expect(text).toHaveFocus());
    expect(screen.getByRole('dialog', { name: 'New post' })).toBeInTheDocument();
    expect(onClose).not.toHaveBeenCalled();
  });

  it('calls onClose once after "Discard"', async () => {
    const onClose = vi.fn();
    await openAndType(onClose);
    await userEvent.keyboard('{Escape}');
    const question = await screen.findByRole('dialog', { name: 'Discard your text?' });

    await userEvent.click(within(question).getByRole('button', { name: 'Discard' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(onClose).toHaveBeenCalledOnce();
  });

  it('asks on a click outside the panel, like Escape', async () => {
    const onClose = vi.fn();
    await openAndType(onClose);

    // The backdrop is outside the panel
    const backdrop = document.querySelector('[class*="bg-black/70"]');
    expect(backdrop).not.toBeNull();
    await userEvent.click(backdrop as HTMLElement);

    expect(await screen.findByRole('dialog', { name: 'Discard your text?' })).toBeInTheDocument();
    expect(onClose).not.toHaveBeenCalled();
  });

  it('asks on the close button and on a DialogCloseButton', async () => {
    const onClose = vi.fn();
    const { dialog } = await openAndType(onClose);

    await userEvent.click(within(dialog).getByRole('button', { name: 'Close' }));
    let question = await screen.findByRole('dialog', { name: 'Discard your text?' });
    await userEvent.click(within(question).getByRole('button', { name: 'Keep writing' }));
    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Discard your text?' })).not.toBeInTheDocument());

    await userEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));
    question = await screen.findByRole('dialog', { name: 'Discard your text?' });
    expect(onClose).not.toHaveBeenCalled();
    await userEvent.click(within(question).getByRole('button', { name: 'Discard' }));
    await waitFor(() => expect(onClose).toHaveBeenCalledOnce());
  });

  it('closes at once with Escape when there are no unsaved changes, as before', async () => {
    const onClose = vi.fn();
    await openAndType(onClose, false);

    await userEvent.keyboard('{Escape}');

    expect(onClose).toHaveBeenCalledOnce();
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(screen.queryByRole('dialog', { name: 'Discard your text?' })).not.toBeInTheDocument();
  });

  it('closes at once on a click outside the panel when there are no unsaved changes', async () => {
    const onClose = vi.fn();
    await openAndType(onClose, false);

    await userEvent.click(document.querySelector('[class*="bg-black/70"]') as HTMLElement);

    expect(onClose).toHaveBeenCalledOnce();
  });
});

describe('Drawer', () => {
  it('is a named dialog with a close button', async () => {
    const onClose = vi.fn();
    render(
      <Drawer open onClose={onClose} title="Menu" side="right">
        <a href="/dashboard">Dashboard</a>
      </Drawer>,
    );
    expect(await screen.findByRole('dialog', { name: 'Menu' })).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Close menu' }));
    expect(onClose).toHaveBeenCalled();
  });
});

describe('DropdownMenu', () => {
  it('opens with the keyboard, moves with the arrows and activates an entry', async () => {
    const onEdit = vi.fn();
    const onLogout = vi.fn();
    render(
      <DropdownMenu label="User menu" trigger={<span>AL</span>}>
        <DropdownMenuItem>
          <button type="button" className={dropdownMenuItemClasses} onClick={onEdit}>
            Edit profile
          </button>
        </DropdownMenuItem>
        <DropdownMenuItem>
          <button type="button" className={dropdownMenuItemClasses} onClick={onLogout}>
            Log out
          </button>
        </DropdownMenuItem>
      </DropdownMenu>,
    );

    const trigger = screen.getByRole('button', { name: 'User menu' });
    trigger.focus();
    await userEvent.keyboard('{Enter}');

    expect(await screen.findByRole('menu')).toBeInTheDocument();
    expect(screen.getAllByRole('menuitem')).toHaveLength(2);

    await userEvent.keyboard('{ArrowDown}');
    await userEvent.keyboard('{Enter}');
    expect(onLogout).toHaveBeenCalledOnce();
    expect(onEdit).not.toHaveBeenCalled();
    await waitFor(() => expect(screen.queryByRole('menu')).not.toBeInTheDocument());
  });
});

describe('Select', () => {
  const options = [
    { value: 'beginner', label: 'Beginner' },
    { value: 'expert', label: 'Expert' },
  ] as const;

  function SelectHarness() {
    const [value, setValue] = useState<'beginner' | 'expert' | null>(null);
    return (
      <>
        <label htmlFor="level">Level</label>
        <Select id="level" options={options} value={value} onChange={setValue} placeholder="Select a level" />
      </>
    );
  }

  it('shows the placeholder, then the chosen option', async () => {
    render(<SelectHarness />);
    const button = screen.getByRole('button', { name: /Level/ });
    expect(button).toHaveTextContent('Select a level');

    await userEvent.click(button);
    await userEvent.click(await screen.findByRole('option', { name: 'Expert' }));

    await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument());
    expect(button).toHaveTextContent('Expert');
  });

  it('can be used with the keyboard only', async () => {
    render(<SelectHarness />);
    const button = screen.getByRole('button', { name: /Level/ });
    button.focus();
    await userEvent.keyboard('{ArrowDown}');
    expect(await screen.findByRole('listbox')).toBeInTheDocument();
    await userEvent.keyboard('{ArrowDown}');
    await userEvent.keyboard('{Enter}');
    await waitFor(() => expect(button).toHaveTextContent('Expert'));
  });
});
