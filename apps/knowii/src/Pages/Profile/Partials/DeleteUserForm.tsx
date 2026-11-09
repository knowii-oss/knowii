import { useRoute } from 'ziggy-js';
import type { FormEventHandler } from 'react';
import { useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { DELETE_USER_URL } from '@knowii/common';
import { FaLock } from 'react-icons/fa';
import InputError from '@/Components/InputError';
import ActionSection from '@/Components/ActionSection';
import { Button, Dialog, PasswordInput } from '@/Components/ui';

interface DeleteUserFormProps {
  password: string;
}

export default function DeleteUserForm() {
  const route = useRoute();

  const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);

  const form = useForm<DeleteUserFormProps>({
    password: '',
  });

  const passwordRef = useRef<HTMLInputElement | null>(null);

  const confirmUserDeletion = () => {
    setConfirmingUserDeletion(true);
  };

  const deleteUser: FormEventHandler = (e) => {
    e.preventDefault();

    form.delete(route(DELETE_USER_URL), {
      preserveScroll: true,
      onSuccess: () => closeModal(),
      onError: () => passwordRef.current?.focus(),
      onFinish: () => form.reset(),
    });
  };

  const closeModal = () => {
    setConfirmingUserDeletion(false);
    form.reset();
  };

  return (
    <ActionSection title={'Delete Account'} description={'Permanently delete your account.'}>
      <div className="max-w-xl text-sm text-muted">
        Once your account is deleted, your own communities, resources, and interactions will all be permanently removed. Only your public
        profile will remain. If you create a new user account later, you will be able to re-claim your profile, but not the account data.
      </div>

      <div className="mt-5 flex items-center">
        <Button variant="danger" onClick={confirmUserDeletion}>
          Delete Account
        </Button>
      </div>

      <Dialog
        open={confirmingUserDeletion}
        onClose={closeModal}
        title="Are you sure you want to delete your account?"
        footer={
          <>
            <Button variant="secondary" onClick={closeModal}>
              Cancel
            </Button>
            <Button type="submit" form="delete-user-form" variant="danger" loading={form.processing}>
              Go ahead!
            </Button>
          </>
        }
      >
        <form id="delete-user-form" onSubmit={deleteUser}>
          <label htmlFor="delete-user-password" className="text-sm">
            Please enter your password to confirm you would like to permanently delete your account.
          </label>
          <div className="mt-4">
            <PasswordInput
              id="delete-user-password"
              icon={<FaLock />}
              ref={passwordRef}
              value={form.data.password}
              onChange={(e) => form.setData('password', e.target.value)}
              autoFocus
              required
              autoComplete="current-password"
              invalid={!!form.errors.password}
            />
            <InputError className="mt-2" message={form.errors.password} />
          </div>
        </form>
      </Dialog>
    </ActionSection>
  );
}
