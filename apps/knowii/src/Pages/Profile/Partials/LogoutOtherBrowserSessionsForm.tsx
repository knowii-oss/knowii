import { DESTROY_OTHER_BROWSER_SESSIONS_URL, Session } from '@knowii/common';
import { useForm } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import { useRef, useState } from 'react';
import ActionSection from '@/Components/ActionSection';
import { FaDesktop, FaLock, FaMobile } from 'react-icons/fa';
import { Button } from 'primereact/button';
import { Dialog } from 'primereact/dialog';
import { InputText } from 'primereact/inputtext';
import InputError from '@/Components/InputError';
import { Toast } from 'primereact/toast';

interface Props {
  sessions: Session[];
}

interface LogoutOtherBrowserSessionsFormProps {
  password: string;
}

export default function LogoutOtherBrowserSessionsForm(props: Props) {
  const route = useRoute();

  const form = useForm<LogoutOtherBrowserSessionsFormProps>({
    password: '',
  });

  const passwordRef = useRef<HTMLInputElement | null>(null);

  const toastRef = useRef(null);

  const [confirmingLogout, setConfirmingLogout] = useState(false);

  const confirmLogout = () => {
    setConfirmingLogout(true);

    setTimeout(() => passwordRef.current?.focus(), 250);
  };

  const logoutOtherBrowserSessions = () => {
    form.delete(route(DESTROY_OTHER_BROWSER_SESSIONS_URL), {
      preserveScroll: true,
      onSuccess: () => {
        closeModal();
        toastRef.current.show({ severity: 'success', summary: 'Success', detail: 'Logged you out of other browser sessions.' });
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
      <Toast position="bottom-center" ref={toastRef} />

      <div className="max-w-xl text-sm text-gray-600">
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
                  <FaDesktop className="w-8 h-8 text-gray-500" />
                ) : (
                  <FaMobile className="w-8 h-8 text-gray-500" />
                )}
              </div>

              <div className="ml-3">
                <div className="text-sm text-gray-600">
                  {session.agent.platform} - {session.agent.browser}
                </div>

                <div>
                  <div className="text-xs text-gray-500">
                    {session.ip_address},
                    {session.is_current_device ? (
                      <span className="text-green-500 font-semibold">This device</span>
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

      <div className="flex items-center mt-5">
        <Button severity="primary" onClick={confirmLogout}>
          Log out other browser sessions
        </Button>
      </div>

      <Dialog
        header="Log Out Other Browser Sessions"
        visible={confirmingLogout}
        className="w-full sm:w-[75vw] md:w-[60vw] lg:w-[40vw] xl:w-[30vw]"
        onHide={() => closeModal()}
        footer={
          <>
            <Button severity="secondary" label="Cancel" onClick={closeModal} />
            <Button severity="primary" label="Go ahead!" onClick={logoutOtherBrowserSessions} disabled={form.processing} className="ml-2" />
          </>
        }
      >
        Please enter your password to confirm you would like to log out of your other browser sessions across all of your known devices.
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
