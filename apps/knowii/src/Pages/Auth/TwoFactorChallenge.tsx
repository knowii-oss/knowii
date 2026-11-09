import type { FormEventHandler } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import { TWO_FACTOR_LOGIN_URL } from '@knowii/common';
import { FaKey } from 'react-icons/fa';
import AuthenticationCard from '@/Components/AuthenticationCard';
import Footer from '@/Components/Footer';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button, TextInput } from '@/Components/ui';

/**
 * The second step of the sign-in for an account with two-factor authentication (#690): the code of the authenticator
 * app, or a recovery code.
 */
export default function TwoFactorChallengePage() {
  const route = useRoute();
  const [recovery, setRecovery] = useState(false);
  const form = useForm({ code: '', recovery_code: '' });
  const codeInput = useRef<HTMLInputElement>(null);
  const recoveryInput = useRef<HTMLInputElement>(null);

  // Keep the focus on the field that the member must fill when they switch between the two ways
  useEffect(() => {
    (recovery ? recoveryInput : codeInput).current?.focus();
  }, [recovery]);

  const toggle = () => {
    form.setData(recovery ? 'recovery_code' : 'code', '');
    setRecovery(!recovery);
  };

  const submit: FormEventHandler = (event) => {
    event.preventDefault();
    form.post(route(TWO_FACTOR_LOGIN_URL));
  };

  return (
    <>
      <Head title="Two-factor authentication" />
      <AuthenticationCard>
        <p className="mb-4 text-sm text-muted">
          {recovery
            ? 'Enter one of your recovery codes. Each code works once.'
            : 'Enter the code that your authenticator app shows for Knowii.'}
        </p>
        <form onSubmit={submit} noValidate>
          {recovery ? (
            <div>
              <InputLabel htmlFor="recovery_code">Recovery code</InputLabel>
              <TextInput
                ref={recoveryInput}
                id="recovery_code"
                icon={<FaKey />}
                size="lg"
                wrapperClassName="mt-1"
                autoComplete="one-time-code"
                value={form.data.recovery_code}
                onChange={(event) => form.setData('recovery_code', event.target.value)}
                invalid={!!form.errors.recovery_code}
                aria-describedby={form.errors.recovery_code ? 'recovery-code-error' : undefined}
              />
              <InputError id="recovery-code-error" className="mt-2" message={form.errors.recovery_code} />
            </div>
          ) : (
            <div>
              <InputLabel htmlFor="code">Code</InputLabel>
              <TextInput
                ref={codeInput}
                id="code"
                icon={<FaKey />}
                size="lg"
                wrapperClassName="mt-1"
                inputMode="numeric"
                autoComplete="one-time-code"
                autoFocus
                value={form.data.code}
                onChange={(event) => form.setData('code', event.target.value)}
                invalid={!!form.errors.code}
                aria-describedby={form.errors.code ? 'code-error' : undefined}
              />
              <InputError id="code-error" className="mt-2" message={form.errors.code} />
            </div>
          )}
          <div className="mt-4 flex flex-wrap items-center justify-end gap-3">
            <Button type="button" variant="ghost" size="sm" onClick={toggle}>
              {recovery ? 'Use the code of the app' : 'Use a recovery code'}
            </Button>
            <Button type="submit" loading={form.processing}>
              Sign in
            </Button>
          </div>
        </form>
      </AuthenticationCard>
      <Footer variant="compact" />
    </>
  );
}
