import React, { useRef, useState, FormEventHandler } from 'react';
import { useForm } from '@inertiajs/react';
import ActionMessage from '@/Components/ActionMessage';
import ActionSection from '@/Components/ActionSection';
import DialogModal from '@/Components/DialogModal';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';

interface LogoutOtherBrowserSessionsFormProps {
  sessions: Array<{
    agent: {
      is_desktop: boolean;
      platform: string | null;
      browser: string | null;
    };
    ip_address: string;
    is_current_device: boolean;
    last_active: string;
  }>;
}

const LogoutOtherBrowserSessionsForm: React.FC<LogoutOtherBrowserSessionsFormProps> = ({ sessions }) => {
  const [confirmingLogout, setConfirmingLogout] = useState(false);
  const passwordInput = useRef<HTMLInputElement>(null);

  const form = useForm({
    password: '',
  });

  const confirmLogout = () => {
    setConfirmingLogout(true);

    setTimeout(() => passwordInput.current?.focus(), 250);
  };

  const logoutOtherBrowserSessions: FormEventHandler = (e) => {
    e.preventDefault();
    form.delete(route('other-browser-sessions.destroy'), {
      preserveScroll: true,
      onSuccess: () => closeModal(),
      onError: () => passwordInput.current?.focus(),
      onFinish: () => form.reset(),
    });
  };

  const closeModal = () => {
    setConfirmingLogout(false);
    form.reset();
  };

  return (
    <ActionSection
      title="Browser Sessions"
      description="Manage and log out your active sessions on other browsers and devices."
      content={
        <>
          <div className="max-w-xl text-sm text-gray-600 dark:text-gray-400">
            If necessary, you may log out of all of your other browser sessions across all of your devices. Some of your recent sessions are
            listed below; however, this list may not be exhaustive. If you feel your account has been compromised, you should also update
            your password.
          </div>

          {sessions.length > 0 && (
            <div className="mt-5 space-y-6">
              {sessions.map((session, i) => (
                <div key={i} className="flex items-center">
                  <div>
                    {session.agent.is_desktop ? (
                      <svg
                        className="w-8 h-8 text-gray-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        strokeWidth="1.5"
                        stroke="currentColor"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"
                        />
                      </svg>
                    ) : (
                      <svg
                        className="w-8 h-8 text-gray-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        strokeWidth="1.5"
                        stroke="currentColor"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"
                        />
                      </svg>
                    )}
                  </div>

                  <div className="ms-3">
                    <div className="text-sm text-gray-600 dark:text-gray-400">
                      {session.agent.platform ? session.agent.platform : 'Unknown'} -{' '}
                      {session.agent.browser ? session.agent.browser : 'Unknown'}
                    </div>

                    <div>
                      <div className="text-xs text-gray-500">
                        {session.ip_address},
                        {session.is_current_device ? (
                          <span className="font-semibold text-green-500"> This device</span>
                        ) : (
                          <> Last active {session.last_active}</>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}

          <div className="flex items-center mt-5">
            <PrimaryButton onClick={confirmLogout}>Log Out Other Browser Sessions</PrimaryButton>
            <ActionMessage on={form.recentlySuccessful} className="ms-3">
              Done.
            </ActionMessage>
          </div>

          <DialogModal
            show={confirmingLogout}
            onClose={closeModal}
            title="Log Out Other Browser Sessions"
            content={
              <>
                Please enter your password to confirm you would like to log out of your other browser sessions across all of your devices.
                <div className="mt-4">
                  <TextInput
                    ref={passwordInput}
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    type="password"
                    className="block w-3/4 mt-1"
                    placeholder="Password"
                    autoComplete="current-password"
                    onKeyUp={(e) => e.key === 'Enter' && logoutOtherBrowserSessions(e)}
                  />
                  <InputError message={form.errors.password} className="mt-2" />
                </div>
              </>
            }
            footer={
              <>
                <SecondaryButton onClick={closeModal}>Cancel</SecondaryButton>
                <PrimaryButton
                  className={form.processing ? 'ms-3 opacity-25' : 'ms-3'}
                  disabled={form.processing}
                  onClick={logoutOtherBrowserSessions}
                >
                  Log Out Other Browser Sessions
                </PrimaryButton>
              </>
            }
          />
        </>
      }
    />
  );
};

export default LogoutOtherBrowserSessionsForm;
