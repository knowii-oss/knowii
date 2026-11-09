import type { ReactNode } from 'react';
import { useRef, useState } from 'react';
import axios from 'axios';
import { useRoute } from 'ziggy-js';
import { PASSWORD_CONFIRM_URL, PASSWORD_CONFIRMATION_URL } from '@knowii/common';
import { FaLock } from 'react-icons/fa';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button, Dialog, PasswordInput } from '@/Components/ui';

interface Props {
  title?: string;
  content?: string;
  button?: string;
  onConfirm: () => void;
  // The control that starts the action: a button, usually
  children: (start: () => void) => ReactNode;
}

/**
 * Asks for the password before a sensitive action (two-factor settings, #690), unless it was confirmed a few minutes
 * ago (Fortify's password.confirm middleware).
 */
export default function ConfirmsPassword({
  title = 'Confirm your password',
  content = 'For your security, confirm your password to continue.',
  button = 'Confirm',
  onConfirm,
  children,
}: Props) {
  const route = useRoute();
  const [open, setOpen] = useState(false);
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | undefined>(undefined);
  const [processing, setProcessing] = useState(false);
  const input = useRef<HTMLInputElement>(null);

  const start = () => {
    void axios.get(route(PASSWORD_CONFIRMATION_URL)).then((response) => {
      if ((response.data as { confirmed?: boolean }).confirmed) {
        onConfirm();
      } else {
        setOpen(true);
      }
    });
  };

  const close = () => {
    setOpen(false);
    setPassword('');
    setError(undefined);
  };

  const confirm = () => {
    setProcessing(true);
    axios
      .post(route(PASSWORD_CONFIRM_URL), { password })
      .then(() => {
        setProcessing(false);
        close();
        onConfirm();
      })
      .catch((reason: { response?: { data?: { errors?: { password?: string[] } } } }) => {
        setProcessing(false);
        setError(reason.response?.data?.errors?.password?.[0] ?? 'The password is not correct.');
        input.current?.focus();
      });
  };

  return (
    <>
      {children(start)}
      <Dialog
        open={open}
        onClose={close}
        size="sm"
        title={title}
        description={content}
        footer={
          <>
            <Button variant="secondary" onClick={close}>
              Cancel
            </Button>
            <Button type="submit" form="confirm-password-form" loading={processing}>
              {button}
            </Button>
          </>
        }
      >
        <form
          id="confirm-password-form"
          onSubmit={(event) => {
            event.preventDefault();
            confirm();
          }}
        >
          <InputLabel htmlFor="confirm-password">Password</InputLabel>
          <PasswordInput
            ref={input}
            id="confirm-password"
            icon={<FaLock />}
            wrapperClassName="mt-1"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            autoComplete="current-password"
            autoFocus
            invalid={!!error}
            aria-describedby={error ? 'confirm-password-error' : undefined}
          />
          <InputError id="confirm-password-error" className="mt-2" message={error} />
        </form>
      </Dialog>
    </>
  );
}
