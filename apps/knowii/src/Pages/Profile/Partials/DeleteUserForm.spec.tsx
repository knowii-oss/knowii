import { beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { AccountDeletionState, InstancePageProp } from '@knowii/common';
import { formatFullDateTime, readKeptText, writeKeptText } from '@knowii/common';
import DeleteUserForm from './DeleteUserForm';

const send = vi.fn();
const post = vi.fn();

// The values of the instance (#1653): the texts name its contact address
const pageProps: { auth: { user: { cuid: string } }; instance?: InstancePageProp } = { auth: { user: { cuid: 'ada1' } } };

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
    router: { post: (url: string, data: unknown, options: unknown) => post(url, data, options) },
    usePage: () => ({ props: pageProps }),
  };
});
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));

const UNTIL = '2026-11-18T10:05:00+00:00';

function deletionState(state: Partial<AccountDeletionState> = {}): AccountDeletionState {
  return {
    proof: 'sign_in',
    label: 'Example account',
    confirmed_until: null,
    two_factor: false,
    status: null,
    error: null,
    delete_content: null,
    ...state,
  };
}

async function openDialog(deletion?: AccountDeletionState) {
  render(<DeleteUserForm deletion={deletion} />);
  await userEvent.click(screen.getByRole('button', { name: 'Delete Account' }));
}

beforeEach(() => {
  pageProps.instance = { contactEmail: 'contact@example.com', publisher: null, footerNote: null, footerLinks: [], socialLinks: [] };
});

describe('DeleteUserForm (#1344)', () => {
  it('tells what stays and offers the box, not ticked by default', async () => {
    await openDialog();

    expect(
      screen.getByText(
        'Your posts, comments, resources and messages stay in their communities under the name "Deleted member". Delete what you want removed before you delete your account, or tick the box below. Afterwards, write to contact@example.com to have content removed.',
      ),
    ).toBeInTheDocument();
    const box = screen.getByRole('checkbox', { name: 'Also delete my posts, comments and messages' });
    expect(box).not.toBeChecked();
  });

  it('names the operator of the site when the instance gives no contact address (#1653)', async () => {
    delete pageProps.instance;
    await openDialog();

    expect(
      screen.getByText(
        'Your posts, comments, resources and messages stay in their communities under the name "Deleted member". Delete what you want removed before you delete your account, or tick the box below. Afterwards, write to the operator of this site to have content removed.',
      ),
    ).toBeInTheDocument();
  });

  it('with a password and two-factor authentication, asks for the code after the password', async () => {
    await openDialog(deletionState({ proof: 'password', two_factor: true }));

    const password = screen.getByLabelText('Please enter your password to confirm you would like to permanently delete your account.');
    expect(password).toHaveFocus();
    await userEvent.type(password, 'secret');
    await userEvent.type(screen.getByLabelText('Enter the code of your authenticator app or a recovery code.'), '123456');
    await userEvent.click(screen.getByRole('button', { name: 'Go ahead!' }));

    expect(send).toHaveBeenLastCalledWith(
      '/current-user.destroy',
      { password: 'secret', delete_content: false, code: '123456' },
      expect.anything(),
    );
  });

  it('with a password and without two-factor authentication, shows no code field', async () => {
    await openDialog(deletionState({ proof: 'password' }));

    expect(screen.queryByLabelText('Enter the code of your authenticator app or a recovery code.')).not.toBeInTheDocument();
  });

  it('sends delete_content false when the box stays off', async () => {
    await openDialog();

    await userEvent.type(screen.getByLabelText(/Please enter your password/), 'secret');
    await userEvent.click(screen.getByRole('button', { name: 'Go ahead!' }));

    expect(send).toHaveBeenLastCalledWith(
      '/current-user.destroy',
      { password: 'secret', delete_content: false, code: '' },
      expect.anything(),
    );
  });

  it('sends delete_content true when the box is ticked, with the keyboard too', async () => {
    await openDialog();

    await userEvent.type(screen.getByLabelText(/Please enter your password/), 'secret');
    const box = screen.getByRole('checkbox', { name: 'Also delete my posts, comments and messages' });
    box.focus();
    await userEvent.keyboard(' ');
    expect(box).toBeChecked();
    await userEvent.click(screen.getByRole('button', { name: 'Go ahead!' }));

    expect(send).toHaveBeenLastCalledWith(
      '/current-user.destroy',
      { password: 'secret', delete_content: true, code: '' },
      expect.anything(),
    );
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

describe('DeleteUserForm for an account without a password (#1521)', () => {
  it('has no password field: it explains the step and offers to confirm with the provider', async () => {
    await openDialog(deletionState());

    expect(screen.queryByLabelText(/Please enter your password/)).not.toBeInTheDocument();
    expect(
      screen.getByText(
        'Your account has no password. To confirm that it is you, sign in again with Example account. You then come back here, and you can delete your account for a few minutes.',
      ),
    ).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Delete my account' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Confirm with Example account' })).toHaveFocus();
    expect(screen.getByRole('checkbox', { name: 'Also delete my posts, comments and messages' })).not.toBeChecked();
  });

  it('starts the proof with a POST that carries the choice of the box', async () => {
    await openDialog(deletionState());

    await userEvent.click(screen.getByRole('checkbox', { name: 'Also delete my posts, comments and messages' }));
    await userEvent.click(screen.getByRole('button', { name: 'Confirm with Example account' }));

    expect(post).toHaveBeenLastCalledWith('/oidc.confirm-deletion', { delete_content: true }, expect.anything());
    expect(send).not.toHaveBeenCalledWith('/current-user.destroy', expect.anything(), expect.anything());
  });

  it('after a valid proof, opens again by itself, says until when it holds, and keeps the box', async () => {
    send.mockClear();
    render(<DeleteUserForm deletion={deletionState({ confirmed_until: UNTIL, status: UNTIL, delete_content: true })} />);

    const status = await screen.findByRole('status');
    expect(status).toHaveTextContent(
      `You confirmed with Example account. You can delete your account until ${formatFullDateTime(new Date(UNTIL), null)}.`,
    );
    expect(screen.queryByRole('button', { name: 'Confirm with Example account' })).not.toBeInTheDocument();
    expect(screen.queryByLabelText(/Please enter your password/)).not.toBeInTheDocument();
    const box = screen.getByRole('checkbox', { name: 'Also delete my posts, comments and messages' });
    expect(box).toBeChecked();
    const final = screen.getByRole('button', { name: 'Delete my account' });
    expect(final).toHaveFocus();

    await userEvent.click(final);
    expect(send).toHaveBeenLastCalledWith('/current-user.destroy', { password: '', delete_content: true, code: '' }, expect.anything());
  });

  it('after a valid proof, asks for the code when two-factor authentication is on', async () => {
    render(<DeleteUserForm deletion={deletionState({ confirmed_until: UNTIL, status: UNTIL, two_factor: true })} />);

    const code = await screen.findByLabelText('Enter the code of your authenticator app or a recovery code.');
    expect(code).toHaveFocus();
    await userEvent.type(code, '123456');
    await userEvent.click(screen.getByRole('button', { name: 'Delete my account' }));

    expect(send).toHaveBeenLastCalledWith(
      '/current-user.destroy',
      { password: '', delete_content: false, code: '123456' },
      expect.anything(),
    );
  });

  it('after a refused proof, opens again with the reason as an alert and offers to try again, with the box as it was', async () => {
    render(
      <DeleteUserForm deletion={deletionState({ error: 'The sign-in provider did not confirm a new sign-in.', delete_content: true })} />,
    );

    expect(await screen.findByRole('alert')).toHaveTextContent('The sign-in provider did not confirm a new sign-in.');
    expect(screen.getByRole('button', { name: 'Confirm with Example account' })).toHaveFocus();
    expect(screen.getByRole('checkbox', { name: 'Also delete my posts, comments and messages' })).toBeChecked();
  });

  it('when the dialog that opened by itself closes, the focus goes to the button that opens it', async () => {
    render(<DeleteUserForm deletion={deletionState({ error: 'The sign-in provider did not confirm a new sign-in.' })} />);

    await userEvent.click(await screen.findByRole('button', { name: 'Cancel' }));

    await waitFor(() => expect(screen.getByRole('button', { name: 'Delete Account' })).toHaveFocus(), { timeout: 2000 });
  });

  it('without a way to make the proof, shows no button and tells whom to write to', () => {
    render(<DeleteUserForm deletion={deletionState({ proof: 'unavailable', label: null })} />);

    expect(screen.queryByRole('button', { name: 'Delete Account' })).not.toBeInTheDocument();
    expect(
      screen.getByText(
        'This account has no password and no connected sign-in account, so it cannot confirm its deletion here. Please write to contact@example.com to have it deleted.',
      ),
    ).toBeInTheDocument();
  });

  it('without a way to make the proof and without a contact address, tells to write to the operator of the site (#1653)', () => {
    pageProps.instance = { contactEmail: '', publisher: null, footerNote: null, footerLinks: [], socialLinks: [] };
    render(<DeleteUserForm deletion={deletionState({ proof: 'unavailable', label: null })} />);

    expect(
      screen.getByText(
        'This account has no password and no connected sign-in account, so it cannot confirm its deletion here. Please write to the operator of this site to have it deleted.',
      ),
    ).toBeInTheDocument();
  });
});
