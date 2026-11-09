import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Dialog } from './Dialog';
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
