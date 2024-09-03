import React, { useRef, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthenticationCard from '@/Components/AuthenticationCard';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { FormEventHandler } from 'react';

const TwoFactorConfirmation: React.FC = () => {
  const [recovery, setRecovery] = useState(false);

  const form = useForm({
    code: '',
    recovery_code: '',
  });

  const recoveryCodeInput = useRef<HTMLInputElement>(null);
  const codeInput = useRef<HTMLInputElement>(null);

  const toggleRecovery = async () => {
    setRecovery(!recovery);

    if (recovery) {
      recoveryCodeInput.current?.focus();
      form.setData('code', '');
    } else {
      codeInput.current?.focus();
      form.setData('recovery_code', '');
    }
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    form.post(route('two-factor.login'));
  };

  return (
    <>
      <Head title="Two-factor Confirmation" />

      <AuthenticationCard logo={<AuthenticationCardLogo />}>
        <div className="mb-4 text-sm text-gray-600 dark:text-gray-400">
          {recovery ? (
            <>Please confirm access to your account by entering one of your emergency recovery codes.</>
          ) : (
            <>Please confirm access to your account by entering the authentication code provided by your authenticator application.</>
          )}
        </div>

        <form onSubmit={submit}>
          {!recovery ? (
            <>
              <InputLabel htmlFor="code" value="Code" />
              <TextInput
                id="code"
                ref={codeInput}
                value={form.data.code}
                onChange={(e) => form.setData('code', e.target.value)}
                type="text"
                inputMode="numeric"
                className="block w-full mt-1"
                autoFocus
                autoComplete="one-time-code"
              />
              <InputError className="mt-2" message={form.errors.code} />
            </>
          ) : (
            <>
              <InputLabel htmlFor="recovery_code" value="Recovery Code" />
              <TextInput
                id="recovery_code"
                ref={recoveryCodeInput}
                value={form.data.recovery_code}
                onChange={(e) => form.setData('recovery_code', e.target.value)}
                type="text"
                className="block w-full mt-1"
                autoComplete="one-time-code"
              />
              <InputError className="mt-2" message={form.errors.recovery_code} />
            </>
          )}

          <div className="flex items-center justify-end mt-4">
            <button
              type="button"
              className="text-sm text-gray-600 underline cursor-pointer dark:text-gray-400 hover:text-gray-900"
              onClick={toggleRecovery}
            >
              {recovery ? <>Use an authentication code</> : <>Use a recovery code</>}
            </button>

            <PrimaryButton className={`ms-4 ${form.processing ? 'opacity-25' : ''}`} disabled={form.processing}>
              Log in
            </PrimaryButton>
          </div>
        </form>
      </AuthenticationCard>
    </>
  );
};

export default TwoFactorConfirmation;
