import React, { useEffect, useRef, useState, FormEventHandler } from 'react';
import { useForm, usePage, router } from '@inertiajs/react';
import axios from 'axios';
import ActionSection from '@/Components/ActionSection';
import ConfirmsPassword from '@/Components/ConfirmsPassword';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';

interface TwoFactorAuthenticationFormProps {
  requiresConfirmation: boolean;
}

const TwoFactorAuthenticationForm: React.FC<TwoFactorAuthenticationFormProps> = ({ requiresConfirmation }) => {
  const { props: page } = usePage<any>();

  const [enabling, setEnabling] = useState(false);
  const [confirming, setConfirming] = useState(false);
  const [disabling, setDisabling] = useState(false);
  const [qrCode, setQrCode] = useState<string | null>(null);
  const [setupKey, setSetupKey] = useState<string | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);

  const confirmationForm = useForm({
    code: '',
  });

  const twoFactorEnabled = !enabling && page.auth.user?.two_factor_enabled;

  useEffect(() => {
    if (!twoFactorEnabled) {
      confirmationForm.reset();
      confirmationForm.clearErrors();
    }
  }, [twoFactorEnabled]);

  const enableTwoFactorAuthentication = (e: any) => {
    e.preventDefault();
    setEnabling(true);

    router.post(
      route('two-factor.enable'),
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          Promise.all([showQrCode(), showSetupKey(), showRecoveryCodes()]);
        },
        onFinish: () => {
          setEnabling(false);
          setConfirming(requiresConfirmation);
        },
      },
    );
  };

  const showQrCode = () => {
    return axios.get(route('two-factor.qr-code')).then((response) => {
      setQrCode(response.data.svg);
    });
  };

  const showSetupKey = () => {
    return axios.get(route('two-factor.secret-key')).then((response) => {
      setSetupKey(response.data.secretKey);
    });
  };

  const showRecoveryCodes = () => {
    return axios.get(route('two-factor.recovery-codes')).then((response) => {
      setRecoveryCodes(response.data);
    });
  };

  const confirmTwoFactorAuthentication: FormEventHandler = (e) => {
    e.preventDefault();
    confirmationForm.post(route('two-factor.confirm'), {
      errorBag: 'confirmTwoFactorAuthentication',
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        setConfirming(false);
        setQrCode(null);
        setSetupKey(null);
      },
    });
  };

  const regenerateRecoveryCodes: FormEventHandler = (e) => {
    e.preventDefault();
    axios.post(route('two-factor.recovery-codes')).then(() => showRecoveryCodes());
  };

  const disableTwoFactorAuthentication = () => {
    setDisabling(true);

    router.delete(route('two-factor.disable'), {
      preserveScroll: true,
      onSuccess: () => {
        setDisabling(false);
        setConfirming(false);
      },
    });
  };

  return (
    <ActionSection
      title="Two Factor Authentication"
      description="Add additional security to your account using two factor authentication."
      content={
        <>
          <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
            {twoFactorEnabled && !confirming && 'You have enabled two factor authentication.'}
            {twoFactorEnabled && confirming && 'Finish enabling two factor authentication.'}
            {!twoFactorEnabled && 'You have not enabled two factor authentication.'}
          </h3>

          <div className="max-w-xl mt-3 text-sm text-gray-600 dark:text-gray-400">
            <p>
              When two factor authentication is enabled, you will be prompted for a secure, random token during authentication. You may
              retrieve this token from your phone's Google Authenticator application.
            </p>
          </div>

          {twoFactorEnabled && (
            <div>
              {qrCode && (
                <>
                  <div className="max-w-xl mt-4 text-sm text-gray-600 dark:text-gray-400">
                    <p className="font-semibold">
                      {confirming
                        ? "To finish enabling two factor authentication, scan the following QR code using your phone's authenticator application or enter the setup key and provide the generated OTP code."
                        : "Two factor authentication is now enabled. Scan the following QR code using your phone's authenticator application or enter the setup key."}
                    </p>
                  </div>

                  <div
                    className="inline-block p-2 mt-4 bg-white"
                    dangerouslySetInnerHTML={{
                      __html: qrCode,
                    }}
                  />

                  {setupKey && (
                    <div className="max-w-xl mt-4 text-sm text-gray-600 dark:text-gray-400">
                      <p className="font-semibold">
                        Setup Key: <span>{setupKey}</span>
                      </p>
                    </div>
                  )}

                  {confirming && (
                    <div className="mt-4">
                      <InputLabel htmlFor="code" value="Code" />

                      <TextInput
                        id="code"
                        value={confirmationForm.data.code}
                        onChange={(e) => confirmationForm.setData('code', e.target.value)}
                        type="text"
                        name="code"
                        className="block w-1/2 mt-1"
                        inputMode="numeric"
                        autoFocus
                        autoComplete="one-time-code"
                        onKeyUp={(e) => e.key === 'Enter' && confirmTwoFactorAuthentication(e)}
                      />

                      <InputError message={confirmationForm.errors.code} className="mt-2" />
                    </div>
                  )}
                </>
              )}

              {recoveryCodes.length > 0 && !confirming && (
                <>
                  <div className="max-w-xl mt-4 text-sm text-gray-600 dark:text-gray-400">
                    <p className="font-semibold">
                      Store these recovery codes in a secure password manager. They can be used to recover access to your account if your
                      two factor authentication device is lost.
                    </p>
                  </div>

                  <div className="grid max-w-xl gap-1 px-4 py-4 mt-4 font-mono text-sm bg-gray-100 rounded-lg dark:bg-gray-900 dark:text-gray-100">
                    {recoveryCodes.map((code) => (
                      <div key={code}>{code}</div>
                    ))}
                  </div>
                </>
              )}
            </div>
          )}

          <div className="mt-5">
            {!twoFactorEnabled ? (
              <ConfirmsPassword onConfirmed={enableTwoFactorAuthentication}>
                <PrimaryButton type="button" className={enabling ? 'opacity-25' : ''} disabled={enabling}>
                  Enable
                </PrimaryButton>
              </ConfirmsPassword>
            ) : (
              <>
                {confirming && (
                  <ConfirmsPassword onConfirmed={confirmTwoFactorAuthentication}>
                    <PrimaryButton type="button" className={`me-3 ${enabling ? 'opacity-25' : ''}`} disabled={enabling}>
                      Confirm
                    </PrimaryButton>
                  </ConfirmsPassword>
                )}

                {recoveryCodes.length > 0 && !confirming && (
                  <ConfirmsPassword onConfirmed={regenerateRecoveryCodes}>
                    <SecondaryButton className="me-3">Regenerate Recovery Codes</SecondaryButton>
                  </ConfirmsPassword>
                )}

                {recoveryCodes.length === 0 && !confirming && (
                  <ConfirmsPassword onConfirmed={showRecoveryCodes}>
                    <SecondaryButton className="me-3">Show Recovery Codes</SecondaryButton>
                  </ConfirmsPassword>
                )}

                {confirming && (
                  <ConfirmsPassword onConfirmed={disableTwoFactorAuthentication}>
                    <SecondaryButton className={disabling ? 'opacity-25' : ''} disabled={disabling}>
                      Cancel
                    </SecondaryButton>
                  </ConfirmsPassword>
                )}

                {!confirming && (
                  <ConfirmsPassword onConfirmed={disableTwoFactorAuthentication}>
                    <DangerButton className={disabling ? 'opacity-25' : ''} disabled={disabling}>
                      Disable
                    </DangerButton>
                  </ConfirmsPassword>
                )}
              </>
            )}
          </div>
        </>
      }
    ></ActionSection>
  );
};

export default TwoFactorAuthenticationForm;
