import { useState } from 'react';
import axios from 'axios';
import { router, useForm } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import {
  TWO_FACTOR_CONFIRM_URL,
  TWO_FACTOR_DISABLE_URL,
  TWO_FACTOR_ENABLE_URL,
  TWO_FACTOR_QR_CODE_URL,
  TWO_FACTOR_RECOVERY_CODES_URL,
  TWO_FACTOR_REGENERATE_RECOVERY_CODES_URL,
  TWO_FACTOR_SECRET_KEY_URL,
  useAppData,
  useTypedPage,
} from '@knowii/common';
import { FaKey } from 'react-icons/fa';
import ActionSection from '@/Components/ActionSection';
import ConfirmsPassword from '@/Components/ConfirmsPassword';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button, TextInput } from '@/Components/ui';

interface Props {
  requiresConfirmation: boolean;
}

/**
 * Two-factor authentication (#690) on top of Fortify: turn it on (QR code and setup key), confirm it with a code, see
 * and renew the recovery codes, turn it off. Every step asks for the password first (ConfirmsPassword).
 */
export default function TwoFactorAuthenticationForm({ requiresConfirmation }: Props) {
  const route = useRoute();
  const page = useTypedPage();
  const { showToast } = useAppData();
  const user = page.props.auth.user;
  // Turned on but never confirmed (the page was left during the setup): finish it or cancel it
  const unfinished = requiresConfirmation && user?.two_factor_enabled === true && !user.two_factor_confirmed_at;
  const [enabling, setEnabling] = useState(false);
  const [disabling, setDisabling] = useState(false);
  const [confirming, setConfirming] = useState(unfinished);
  const [qrCode, setQrCode] = useState<string | null>(null);
  const [setupKey, setSetupKey] = useState<string | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const confirmationForm = useForm({ code: '' });

  const twoFactorEnabled = !enabling && user?.two_factor_enabled === true;

  const showQrCode = () => axios.get<{ svg: string }>(route(TWO_FACTOR_QR_CODE_URL)).then((response) => setQrCode(response.data.svg));
  const showSetupKey = () =>
    axios.get<{ secretKey: string }>(route(TWO_FACTOR_SECRET_KEY_URL)).then((response) => setSetupKey(response.data.secretKey));
  const showRecoveryCodes = () =>
    axios.get<string[]>(route(TWO_FACTOR_RECOVERY_CODES_URL)).then((response) => setRecoveryCodes(response.data));

  const enable = () => {
    setEnabling(true);
    router.post(
      route(TWO_FACTOR_ENABLE_URL),
      {},
      {
        preserveScroll: true,
        onSuccess: () => void Promise.all([showQrCode(), showSetupKey(), showRecoveryCodes()]),
        onFinish: () => {
          setEnabling(false);
          setConfirming(requiresConfirmation);
        },
      },
    );
  };

  const confirm = () => {
    confirmationForm.post(route(TWO_FACTOR_CONFIRM_URL), {
      errorBag: 'confirmTwoFactorAuthentication',
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        setConfirming(false);
        setQrCode(null);
        setSetupKey(null);
        showToast({ severity: 'success', summary: 'Two-factor authentication is on' });
      },
    });
  };

  const regenerateRecoveryCodes = () => {
    void axios.post(route(TWO_FACTOR_REGENERATE_RECOVERY_CODES_URL)).then(() => showRecoveryCodes());
  };

  const disable = () => {
    setDisabling(true);
    router.delete(route(TWO_FACTOR_DISABLE_URL), {
      preserveScroll: true,
      onSuccess: () => {
        setConfirming(false);
        setQrCode(null);
        setSetupKey(null);
        setRecoveryCodes([]);
        showToast({ severity: 'success', summary: 'Two-factor authentication is off' });
      },
      onFinish: () => setDisabling(false),
    });
  };

  const status = twoFactorEnabled && !confirming ? 'on' : confirming ? 'finishing' : 'off';

  return (
    <ActionSection
      title="Two-factor authentication"
      description="A code from an authenticator app on your phone, asked at each sign-in, after your password."
    >
      <h3 className="text-lg font-bold" aria-live="polite">
        {status === 'on' && 'Two-factor authentication is on.'}
        {status === 'finishing' && 'Finish turning on two-factor authentication.'}
        {status === 'off' && 'Two-factor authentication is off.'}
      </h3>
      <p className="mt-2 max-w-xl text-sm text-muted">
        At each sign-in, you enter a code from an authenticator app (for example Aegis, 2FAS or 1Password) after your password.
      </p>

      {qrCode && (
        <div className="mt-4 flex flex-col gap-4">
          <p className="max-w-xl text-sm">
            {confirming
              ? 'Scan this QR code with your authenticator app, or enter the setup key. Then enter the code that the app shows.'
              : 'Two-factor authentication is on. Scan this QR code with your authenticator app, or enter the setup key.'}
          </p>
          {/* An image, not markup: the SVG of the server never runs in the page */}
          <img
            src={`data:image/svg+xml;base64,${btoa(qrCode)}`}
            alt="QR code to scan with your authenticator app"
            className="size-48 rounded-lg bg-white p-2"
          />
          {setupKey && (
            <p className="text-sm">
              Setup key: <code className="font-mono font-bold select-all">{setupKey}</code>
            </p>
          )}
          {confirming && (
            <form
              className="flex max-w-xs flex-col gap-2"
              onSubmit={(event) => {
                event.preventDefault();
                confirm();
              }}
            >
              <InputLabel htmlFor="two-factor-code">Code</InputLabel>
              <TextInput
                id="two-factor-code"
                inputMode="numeric"
                autoFocus
                autoComplete="one-time-code"
                value={confirmationForm.data.code}
                onChange={(event) => confirmationForm.setData('code', event.target.value)}
                invalid={!!confirmationForm.errors.code}
                aria-describedby={confirmationForm.errors.code ? 'two-factor-code-error' : undefined}
              />
              <InputError id="two-factor-code-error" message={confirmationForm.errors.code} />
            </form>
          )}
        </div>
      )}

      {recoveryCodes.length > 0 && !confirming && (
        <div className="mt-4 flex flex-col gap-2">
          <p className="max-w-xl text-sm">
            Keep these recovery codes in a password manager. Each one lets you sign in once if you lose your phone.
          </p>
          <ul aria-label="Recovery codes" className="grid max-w-xl gap-1 rounded-lg bg-well p-4 font-mono text-sm">
            {recoveryCodes.map((code) => (
              <li key={code}>{code}</li>
            ))}
          </ul>
        </div>
      )}

      <div className="mt-5 flex flex-wrap gap-3">
        {!twoFactorEnabled ? (
          <ConfirmsPassword onConfirm={enable}>
            {(start) => (
              <Button loading={enabling} leftIcon={<FaKey aria-hidden="true" />} onClick={start}>
                Turn on
              </Button>
            )}
          </ConfirmsPassword>
        ) : (
          <>
            {confirming && !qrCode && (
              <ConfirmsPassword onConfirm={() => void Promise.all([showQrCode(), showSetupKey(), showRecoveryCodes()])}>
                {(start) => <Button onClick={start}>Show the QR code</Button>}
              </ConfirmsPassword>
            )}
            {confirming && qrCode && (
              <ConfirmsPassword onConfirm={confirm}>
                {(start) => (
                  <Button loading={confirmationForm.processing} onClick={start}>
                    Confirm
                  </Button>
                )}
              </ConfirmsPassword>
            )}
            {recoveryCodes.length > 0 && !confirming && (
              <ConfirmsPassword onConfirm={regenerateRecoveryCodes}>
                {(start) => (
                  <Button variant="secondary" onClick={start}>
                    New recovery codes
                  </Button>
                )}
              </ConfirmsPassword>
            )}
            {recoveryCodes.length === 0 && !confirming && (
              <ConfirmsPassword onConfirm={() => void showRecoveryCodes()}>
                {(start) => (
                  <Button variant="secondary" onClick={start}>
                    Show the recovery codes
                  </Button>
                )}
              </ConfirmsPassword>
            )}
            <ConfirmsPassword onConfirm={disable}>
              {(start) => (
                <Button variant={confirming ? 'secondary' : 'danger'} loading={disabling} onClick={start}>
                  {confirming ? 'Cancel' : 'Turn off'}
                </Button>
              )}
            </ConfirmsPassword>
          </>
        )}
      </div>
    </ActionSection>
  );
}
