import type { Session } from '@knowii/common';
import { DESTROY_OTHER_BROWSER_SESSIONS_URL, useAppData } from '@knowii/common';
import { useForm } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import type { FormEventHandler } from 'react';
import { useRef, useState } from 'react';
import ActionSection from '@/Components/ActionSection';
import { FaDesktop, FaLock, FaMobile } from 'react-icons/fa';
import InputError from '@/Components/InputError';
import { Button, Dialog, PasswordInput } from '@/Components/ui';

interface Props {
  sessions: Session[];
}

interface LogoutOtherBrowserSessionsFormProps {
  password: string;
}

export default function LogoutOtherBrowserSessionsForm(props: Props) {
  const route = useRoute();

  const { showToast } = useAppData();

  const form = useForm<LogoutOtherBrowserSessionsFormProps>({
    password: '',
  });

  const passwordRef = useRef<HTMLInputElement | null>(null);

  const [confirmingLogout, setConfirmingLogout] = useState(false);

  const confirmLogout = () => {
    setConfirmingLogout(true);
  };

  const logoutOtherBrowserSessions: FormEventHandler = (e) => {
    e.preventDefault();

    form.delete(route(DESTROY_OTHER_BROWSER_SESSIONS_URL), {
      preserveScroll: true,
      onSuccess: () => {
        closeModal();
        showToast({ severity: 'success', summary: 'Success', detail: 'Logged you out of other browser sessions.' });
      },
      onError: () => passwordRef.current?.focus(),
      onFinish: () => form.reset(),
    });
  };

  const closeModal = () => {
    setConfirmingLogout(false);
    form.reset();
  };

  return (
    <ActionSection title={'Browser Sessions'} description={'Manage and log out your active sessions on other browsers and devices.'}>
      <div className="max-w-xl text-sm text-muted">
        If necessary, you may log out of all of your other browser sessions across all of your devices. Some of your recent sessions are
        listed below; however, this list may not be exhaustive. If you feel your account has been compromised, you should also update your
        password.
      </div>

      {/* <!-- Other Browser Sessions --> */}
      {props.sessions.length > 0 && (
        <div className="mt-5 space-y-6">
          {props.sessions.map((session, i) => (
            <div className="flex items-center" key={i}>
              <div>
                {session.agent.is_desktop ? (
                  <FaDesktop aria-hidden="true" className="h-8 w-8 text-muted" />
                ) : (
                  <FaMobile aria-hidden="true" className="h-8 w-8 text-muted" />
                )}
              </div>

              <div className="ml-3">
                <div className="text-sm">
                  {session.agent.platform} - {session.agent.browser}
                </div>

                <div>
                  <div className="text-xs text-muted">
                    {session.ip_address},{' '}
                    {session.is_current_device ? (
                      <span className="font-semibold text-success">This device</span>
                    ) : (
                      <span>Last active {session.last_active}</span>
                    )}
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      <div className="mt-5 flex items-center">
        <Button onClick={confirmLogout}>Log out other browser sessions</Button>
      </div>

      <Dialog
        open={confirmingLogout}
        onClose={closeModal}
        title="Log Out Other Browser Sessions"
        footer={
          <>
            <Button variant="secondary" onClick={closeModal}>
              Cancel
            </Button>
            <Button type="submit" form="logout-other-sessions-form" loading={form.processing}>
              Go ahead!
            </Button>
          </>
        }
      >
        <form id="logout-other-sessions-form" onSubmit={logoutOtherBrowserSessions}>
          <label htmlFor="logout-other-sessions-password" className="text-sm">
            Please enter your password to confirm you would like to log out of your other browser sessions across all of your known devices.
          </label>
          <div className="mt-4">
            <PasswordInput
              id="logout-other-sessions-password"
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
