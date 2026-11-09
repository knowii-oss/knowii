import { Link, useForm, Head } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { useState } from 'react';
import AuthenticationCard from '@/Components/AuthenticationCard';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { useRoute } from 'ziggy-js';
import { FORGOT_PASSWORD_URL, LOGIN_LINK_STORE_URL, LOGIN_URL, REGISTER_URL } from '@knowii/common';
import { FaAt, FaLock } from 'react-icons/fa';
import FooterGuest from '@/Components/FooterGuest';
import { Button, Checkbox, PasswordInput, TextInput } from '@/Components/ui';

interface LoginFormData {
  email: string;
  password: string;
  remember: string;
}

interface Props {
  canResetPassword: boolean;
  status: string;
}

export default function LoginPage({ canResetPassword, status }: Props) {
  const route = useRoute();

  const form = useForm<LoginFormData>({
    email: '',
    password: '',
    remember: '',
  });

  // Sign in with a link sent by email instead of the password (#703)
  const [linkMode, setLinkMode] = useState(false);
  const linkForm = useForm({ email: '' });
  const sendLink: FormEventHandler = (event) => {
    event.preventDefault();
    linkForm.post(route(LOGIN_LINK_STORE_URL), { onSuccess: () => setLinkMode(false) });
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    form.post(route(LOGIN_URL), {
      onFinish: () => form.reset('password'),
    });
  };

  return (
    <>
      <Head title="Log in" />
      <AuthenticationCard>
        {status && (
          <div role="status" className="mb-4 text-sm font-medium text-success">
            {status}
          </div>
        )}

        {linkMode ? (
          <form onSubmit={sendLink} className="flex flex-col gap-4">
            <p className="text-sm text-muted">
              We email you a link that signs you in, without your password. It works once, for 15 minutes.
            </p>
            <div>
              <InputLabel htmlFor="link-email">Email</InputLabel>
              <TextInput
                id="link-email"
                type="email"
                icon={<FaAt />}
                size="lg"
                wrapperClassName="mt-1"
                value={linkForm.data.email}
                onChange={(event) => linkForm.setData('email', event.target.value)}
                autoComplete="username"
                autoFocus
                required
                invalid={!!linkForm.errors.email}
                aria-describedby={linkForm.errors.email ? 'link-email-error' : undefined}
              />
              <InputError id="link-email-error" className="mt-2" message={linkForm.errors.email} />
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <Button type="button" variant="ghost" size="sm" onClick={() => setLinkMode(false)}>
                Use my password
              </Button>
              <Button type="submit" size="lg" loading={linkForm.processing}>
                Email me a link
              </Button>
            </div>
          </form>
        ) : (
          <form onSubmit={submit}>
            {/* Email */}
            <div>
              <InputLabel htmlFor="email">Email</InputLabel>
              <TextInput
                icon={<FaAt />}
                size="lg"
                wrapperClassName="mt-1"
                id="email"
                type="email"
                value={form.data.email}
                onChange={(e) => form.setData('email', e.target.value)}
                autoComplete="username"
                autoFocus={true}
                required
                disabled={form.processing}
              />
              <InputError className="mt-2" message={form.errors.email} />
            </div>

            {/* Password */}
            <div className="mt-4">
              <InputLabel htmlFor="password">Password</InputLabel>
              <PasswordInput
                icon={<FaLock />}
                size="lg"
                wrapperClassName="mt-1"
                id="password"
                value={form.data.password}
                onChange={(e) => form.setData('password', e.target.value)}
                required
                autoComplete="current-password"
                disabled={form.processing}
              />
              <InputError className="mt-2" message={form.errors.password} />
            </div>

            {/* Remember me */}
            <div className="mt-4">
              <label className="flex w-fit cursor-pointer items-center gap-2">
                <Checkbox
                  name="remember"
                  checked={form.data.remember === 'on'}
                  onChange={(e) => form.setData('remember', e.target.checked ? 'on' : '')}
                  disabled={form.processing}
                />
                <span className="text-sm text-muted">Remember me</span>
              </label>
            </div>

            {/* Actions */}
            <div className="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div className="flex flex-col sm:flex-row sm:items-center sm:gap-4">
                {/* Password reset */}
                {canResetPassword && (
                  <Link href={route(FORGOT_PASSWORD_URL)} className="simple-link touch-target">
                    Forgot your password?
                  </Link>
                )}

                {/* Register */}
                <Link href={route(REGISTER_URL)} className="simple-link touch-target">
                  Need an account?
                </Link>
              </div>

              <div className="mt-4 sm:mt-0 flex justify-center sm:justify-start">
                {/* Log in */}
                <Button type="submit" size="lg" className="w-full sm:w-auto" loading={form.processing}>
                  Log in
                </Button>
              </div>
            </div>
          </form>
        )}
        {!linkMode && (
          <div className="mt-6 border-t border-border pt-4 text-center">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => {
                linkForm.setData('email', form.data.email);
                setLinkMode(true);
              }}
            >
              Email me a sign-in link instead
            </Button>
          </div>
        )}
      </AuthenticationCard>
      <FooterGuest />
    </>
  );
}
