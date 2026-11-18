import { useRoute } from 'ziggy-js';
import type { FormEventHandler, ReactNode } from 'react';
import { useRef, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import type { AccountDeletionState } from '@knowii/common';
import {
  DELETE_USER_URL,
  OIDC_CONFIRM_DELETION_URL,
  formatFullDateTime,
  metadata,
  removeKeptTextsOfAccount,
  useTypedPage,
} from '@knowii/common';
import { FaLock } from 'react-icons/fa';
import InputError from '@/Components/InputError';
import ActionSection from '@/Components/ActionSection';
import { Button, Checkbox, Dialog, PasswordInput, TextInput, useAccountTimeZone } from '@/Components/ui';

interface DeleteUserFormData {
  password: string;
  // Also delete the posts, comments and messages of the member (#1344, ADR 0043)
  delete_content: boolean;
  // The code of two-factor authentication, for an account without a password (#1521)
  code: string;
}

interface Props {
  /**
   * Which proof the deletion needs (#1521, ADR 0052). Without it, the password.
   */
  deletion?: AccountDeletionState | undefined;
}

/**
 * The deletion of the account (#1261, #1344). The proof of the intent (#1521, ADR 0052):
 *
 * - an account with a password enters it;
 * - an account without a password confirms with a new sign-in at the provider of the site: "Confirm with <label>" goes
 *   to the provider and comes back here, the dialog opens again with the result, and the final button deletes the
 *   account until the time that it shows (with the code when two-factor authentication is on);
 * - an account without a password and without a way to make the proof gets the text that names the contact address.
 *
 * The choice of the box goes with the round trip to the provider and comes back.
 */
export default function DeleteUserForm({ deletion }: Props) {
  const route = useRoute();
  const account = useTypedPage().props.auth.user;
  const zone = useAccountTimeZone();

  const proof = deletion?.proof ?? 'password';
  const label = deletion?.label ?? 'your sign-in account';
  const confirmedUntil = deletion?.confirmed_until ?? null;
  // The member just came back from the provider: the dialog opens again with the result
  const returned = proof === 'sign_in' && (deletion?.status != null || deletion?.error != null);

  // Open from the start after the return from the provider: the result is in the dialog, and the focus goes into it
  const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(returned);
  const [startingProof, setStartingProof] = useState(false);

  const form = useForm<DeleteUserFormData>({
    password: '',
    delete_content: deletion?.delete_content ?? false,
    code: '',
  });

  const passwordRef = useRef<HTMLInputElement | null>(null);
  const codeRef = useRef<HTMLInputElement | null>(null);
  const openButtonRef = useRef<HTMLButtonElement | null>(null);

  const confirmUserDeletion = () => {
    setConfirmingUserDeletion(true);
  };

  const deleteUser: FormEventHandler = (e) => {
    e.preventDefault();

    form.delete(route(DELETE_USER_URL), {
      preserveScroll: true,
      onSuccess: () => {
        // The account is gone and signed out: the texts that it did not send leave the browser too (#1622)
        if (account) {
          removeKeptTextsOfAccount(account.cuid);
        }
        closeModal();
      },
      onError: () => (proof === 'password' ? passwordRef.current?.focus() : codeRef.current?.focus()),
      onFinish: () => form.reset('password', 'code'),
    });
  };

  // The server answers with the address of the provider, as a full page visit (#1521)
  const startProof = () => {
    setStartingProof(true);
    router.post(
      route(OIDC_CONFIRM_DELETION_URL),
      { delete_content: form.data.delete_content },
      { onFinish: () => setStartingProof(false) },
    );
  };

  const closeModal = () => {
    setConfirmingUserDeletion(false);
    form.reset('password', 'code');
    // A dialog that opened by itself (after the return from the provider) has no trigger: the focus goes to the button
    // that opens it, once the dialog is gone
    window.setTimeout(() => {
      const active = document.activeElement;
      if (active === null || active === document.body) {
        openButtonRef.current?.focus();
      }
    }, 300);
  };

  const contentBox = (
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
  );

  const whatStays = (
    <p className="mb-4 text-sm">
      {`Your posts, comments, resources and messages stay in their communities under the name "Deleted member". Delete what you want removed before you delete your account, or tick the box below. Afterwards, write to ${metadata.contact.info} to have content removed.`}
    </p>
  );

  // The reason of a refusal: from the provider round trip, or from the deletion itself
  const errors: Partial<Record<string, string>> = form.errors;
  const signInError = deletion?.error ?? errors['sign_in'] ?? null;

  let body: ReactNode;
  let finalButton: ReactNode;
  if (proof === 'password') {
    body = (
      <>
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
      </>
    );
    finalButton = (
      <Button type="submit" form="delete-user-form" variant="danger" loading={form.processing}>
        Go ahead!
      </Button>
    );
  } else if (confirmedUntil === null) {
    body = (
      <>
        {signInError && (
          <p role="alert" className="mb-4 text-sm font-medium text-danger-text">
            {signInError}
          </p>
        )}
        <p className="text-sm">
          {`Your account has no password. To confirm that it is you, sign in again with ${label}. You then come back here, and you can delete your account for a few minutes.`}
        </p>
      </>
    );
    finalButton = (
      <Button variant="danger" loading={startingProof} onClick={startProof} autoFocus>
        {`Confirm with ${label}`}
      </Button>
    );
  } else {
    body = (
      <>
        <p role="status" className="text-sm font-medium text-success">
          {`You confirmed with ${label}. You can delete your account until ${formatFullDateTime(new Date(confirmedUntil), zone)}.`}
        </p>
        {deletion?.two_factor && (
          <div className="mt-4">
            <label htmlFor="delete-user-code" className="text-sm">
              Enter the code of your authenticator app or a recovery code.
            </label>
            <TextInput
              id="delete-user-code"
              ref={codeRef}
              className="mt-2"
              value={form.data.code}
              onChange={(e) => form.setData('code', e.target.value)}
              autoFocus
              required
              inputMode="text"
              autoComplete="one-time-code"
              invalid={!!form.errors.code}
            />
            <InputError className="mt-2" message={form.errors.code} />
          </div>
        )}
      </>
    );
    finalButton = (
      <Button type="submit" form="delete-user-form" variant="danger" loading={form.processing} autoFocus={!deletion?.two_factor}>
        Delete my account
      </Button>
    );
  }

  return (
    <div id="delete-account">
      <ActionSection title={'Delete Account'} description={'Permanently delete your account.'}>
        <div className="max-w-xl text-sm text-muted">
          When you delete your account, we delete your profile, your avatar, your own communities, your memberships, your reactions and your
          API tokens. The posts, comments and resources that you added to the communities of other members stay, with &quot;Deleted
          member&quot; as the author. You cannot undo this.
        </div>

        {proof === 'unavailable' ? (
          // No password and no way to make the proof (#1521): no button, the operator deletes the account
          <p className="mt-5 max-w-xl text-sm font-medium">
            {`This account has no password and no connected sign-in account, so it cannot confirm its deletion here. Please write to ${metadata.contact.info} to have it deleted.`}
          </p>
        ) : (
          <div className="mt-5 flex items-center">
            <Button ref={openButtonRef} variant="danger" onClick={confirmUserDeletion}>
              Delete Account
            </Button>
          </div>
        )}

        <Dialog
          open={confirmingUserDeletion}
          onClose={closeModal}
          title="Are you sure you want to delete your account?"
          footer={
            <>
              <Button variant="secondary" onClick={closeModal}>
                Cancel
              </Button>
              {finalButton}
            </>
          }
        >
          <form id="delete-user-form" onSubmit={deleteUser}>
            {whatStays}
            {body}
            {contentBox}
          </form>
        </Dialog>
      </ActionSection>
    </div>
  );
}
