import { describe, expect, it, vi } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { readKeptText, writeKeptText } from '@knowii/common';
import DeleteUserForm from './DeleteUserForm';

const send = vi.fn();

// A form with real state, to see what the dialog sends
vi.mock('@inertiajs/react', async () => {
  const { useState } = await import('react');
  return {
    useForm: <T extends object>(initial: T) => {
      const [data, setData] = useState<T>(initial);
      return {
        data,
        errors: {},
        processing: false,
        setData: (key: keyof T, value: T[keyof T]) => setData((current) => ({ ...current, [key]: value })),
        reset: () => setData(initial),
        delete: (url: string, options: unknown) => send(url, data, options),
      };
    },
    usePage: () => ({ props: { auth: { user: { cuid: 'ada1' } } } }),
  };
});
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));

async function openDialog() {
  render(<DeleteUserForm />);
  await userEvent.click(screen.getByRole('button', { name: 'Delete Account' }));
}

describe('DeleteUserForm (#1344)', () => {
  it('tells what stays and offers the box, not ticked by default', async () => {
    await openDialog();

    expect(
      screen.getByText(
        'Your posts, comments, resources and messages stay in their communities under the name "Deleted member". Delete what you want removed before you delete your account, or tick the box below. Afterwards, write to contact@developassion.be to have content removed.',
      ),
    ).toBeInTheDocument();
    const box = screen.getByRole('checkbox', { name: 'Also delete my posts, comments and messages' });
    expect(box).not.toBeChecked();
  });

  it('sends delete_content false when the box stays off', async () => {
    await openDialog();

    await userEvent.type(screen.getByLabelText(/Please enter your password/), 'secret');
    await userEvent.click(screen.getByRole('button', { name: 'Go ahead!' }));

    expect(send).toHaveBeenLastCalledWith('/current-user.destroy', { password: 'secret', delete_content: false }, expect.anything());
  });

  it('sends delete_content true when the box is ticked, with the keyboard too', async () => {
    await openDialog();

    await userEvent.type(screen.getByLabelText(/Please enter your password/), 'secret');
    const box = screen.getByRole('checkbox', { name: 'Also delete my posts, comments and messages' });
    box.focus();
    await userEvent.keyboard(' ');
    expect(box).toBeChecked();
    await userEvent.click(screen.getByRole('button', { name: 'Go ahead!' }));

    expect(send).toHaveBeenLastCalledWith('/current-user.destroy', { password: 'secret', delete_content: true }, expect.anything());
  });

  it('says under the box what stays, and links the line to the box (#1603)', async () => {
    await openDialog();

    const box = screen.getByRole('checkbox', { name: 'Also delete my posts, comments and messages' });
    expect(box).toHaveAccessibleDescription(
      'Your resources, pages and lessons stay, under the name "Deleted member". A post or a comment that has replies stays as an empty marker.',
    );
  });

  it('removes the texts that the account did not send once the account is deleted (#1622)', async () => {
    const text = { fields: { title: 'Draft', body: 'Not sent' }, changedAt: Date.now(), base: null };
    writeKeptText({ account: 'ada1', kind: 'post', place: 's1' }, text);
    writeKeptText({ account: 'bob1', kind: 'post', place: 's1' }, text);
    await openDialog();
    await userEvent.type(screen.getByLabelText(/Please enter your password/), 'secret');
    await userEvent.click(screen.getByRole('button', { name: 'Go ahead!' }));
    expect(readKeptText({ account: 'ada1', kind: 'post', place: 's1' })).not.toBeNull();

    const options = send.mock.lastCall?.[2] as { onSuccess: () => void };
    act(() => options.onSuccess());

    expect(readKeptText({ account: 'ada1', kind: 'post', place: 's1' })).toBeNull();
    expect(readKeptText({ account: 'bob1', kind: 'post', place: 's1' })).not.toBeNull();
  });
});
