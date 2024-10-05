import { useRoute } from 'ziggy-js';
import { FormEventHandler, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { DELETE_USER_URL } from '@knowii/common';
import { Button } from 'primereact/button';
import { FaLock } from 'react-icons/fa';
import { InputText } from 'primereact/inputtext';
import InputError from '@/Components/InputError';
import ActionSection from '@/Components/ActionSection';
import { Dialog } from 'primereact/dialog';

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

  const confirmUserDeletion: FormEventHandler = () => {
    setConfirmingUserDeletion(true);

    setTimeout(() => passwordRef.current?.focus(), 250);
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
      <div className="max-w-xl text-sm text-gray-600">
        Once your account is deleted, your own communities, resources, and interactions will all be be permanently removed. Only your public
        profile will remain. If you create a new user account later, you will be able to re-claim your profile, but not the account data.
      </div>

      <div className="flex items-center mt-5">
        <Button label="Delete Account" severity="danger" onClick={confirmUserDeletion} />
      </div>

      <Dialog
        header="Are you sure you want to delete your account?"
        closeOnEscape={true}
        visible={confirmingUserDeletion}
        className="w-full sm:w-[75vw] md:w-[60vw] lg:w-[40vw] xl:w-[30vw]"
        onHide={() => closeModal()}
        footer={
          <>
            <Button severity="secondary" label="Cancel" onClick={closeModal} />
            <Button severity="danger" label="Go ahead!" onClick={deleteUser} disabled={form.processing} className="ml-2" />
          </>
        }
      >
        Please enter your password to confirm you would like to permanently delete your account.
        <div className="mt-4">
          <div className="p-inputgroup mt-1">
            <span className="p-inputgroup-addon mt-1">
              <FaLock />
            </span>
            <InputText
              type="password"
              className="mt-1 w-full"
              ref={passwordRef}
              value={form.data.password}
              onChange={(e) => form.setData('password', e.target.value)}
              required
              autoComplete="current-password"
            />
          </div>
          <InputError className="mt-2" message={form.errors.password} />
        </div>
      </Dialog>
    </ActionSection>
  );
}
