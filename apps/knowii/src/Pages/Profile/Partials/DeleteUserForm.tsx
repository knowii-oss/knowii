import { useRoute } from 'ziggy-js';
import type { FormEventHandler } from 'react';
import { useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { DELETE_USER_URL, metadata } from '@knowii/common';
import { FaLock } from 'react-icons/fa';
import InputError from '@/Components/InputError';
import ActionSection from '@/Components/ActionSection';
import { Button, Checkbox, Dialog, PasswordInput } from '@/Components/ui';

interface DeleteUserFormProps {
  password: string;
  // Also delete the posts, comments and messages of the member (#1344, ADR 0043)
  delete_content: boolean;
}

export default function DeleteUserForm() {
  const route = useRoute();

  const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);

  const form = useForm<DeleteUserFormProps>({
    password: '',
    delete_content: false,
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
        When you delete your account, we delete your profile, your avatar, your own communities, your memberships, your reactions and your
        API tokens. The posts, comments and resources that you added to the communities of other members stay, with &quot;Deleted
        member&quot; as the author. You cannot undo this.
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
          <p className="mb-4 text-sm">
            {`Your posts, comments, resources and messages stay in their communities under the name "Deleted member". Delete what you want removed before you delete your account, or tick the box below. Afterwards, write to ${metadata.contact.info} to have content removed.`}
          </p>
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
          <div className="mt-4">
            <label className="flex cursor-pointer items-start gap-3 text-sm">
              <Checkbox
                name="delete_content"
                checked={form.data.delete_content}
                onChange={(e) => form.setData('delete_content', e.target.checked)}
                aria-invalid={!!form.errors.delete_content}
                aria-describedby="delete-user-content-help"
              />
              <span className="pt-0.5">Also delete my posts, comments and messages</span>
            </label>
            {/* What the box keeps (#1603): words approved by the owner */}
            <p id="delete-user-content-help" className="mt-1 pl-9 text-sm text-muted">
              {
                'Your resources, pages and lessons stay, under the name "Deleted member". A post or a comment that has replies stays as an empty marker.'
              }
            </p>
            <InputError className="mt-2" message={form.errors.delete_content} />
          </div>
        </form>
      </Dialog>
    </ActionSection>
  );
}
