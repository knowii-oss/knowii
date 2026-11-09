import FooterGuest from '@/Components/FooterGuest';
import { Button, PasswordInput, TextInput } from '@/Components/ui';
import AuthenticationCard from '@/Components/AuthenticationCard';
import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { PASSWORD_UPDATE_URL } from '@knowii/common';
import { useRoute } from 'ziggy-js';
import InputLabel from '@/Components/InputLabel';
import { FaAt, FaLock } from 'react-icons/fa';
import InputError from '@/Components/InputError';

interface Props {
  token: string;
  email: string;
}

interface ResetPasswordFormData {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export default function ResetPasswordPage(props: Props) {
  const route = useRoute();

  const form = useForm<ResetPasswordFormData>({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    form.post(route(PASSWORD_UPDATE_URL), {
      onFinish: () => form.reset('password', 'password_confirmation'),
    });
  };

  return (
    <>
      <Head title="Reset Password" />

      <AuthenticationCard>
        <form onSubmit={submit}>
          {/* Email */}
          <div className="mt-4">
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
              showStrength
              id="password"
              value={form.data.password}
              onChange={(e) => form.setData('password', e.target.value)}
              autoComplete="new-password"
              required
              disabled={form.processing}
            />
            <InputError className="mt-2" message={form.errors.password} />
          </div>

          {/* Confirm password */}
          <div className="mt-4">
            <InputLabel htmlFor="password_confirmation">Confirm password</InputLabel>
            <PasswordInput
              icon={<FaLock />}
              size="lg"
              wrapperClassName="mt-1"
              id="password_confirmation"
              value={form.data.password_confirmation}
              onChange={(e) => form.setData('password_confirmation', e.target.value)}
              autoComplete="new-password"
              required
              disabled={form.processing}
            />
            <InputError className="mt-2" message={form.errors.password_confirmation} />
          </div>

          {/* Actions */}
          <div className="mt-4 flex items-center justify-end">
            {/* Reset password */}
            <Button type="submit" className="ml-4" loading={form.processing}>
              Reset password
            </Button>
          </div>
        </form>
      </AuthenticationCard>
      <FooterGuest />
    </>
  );
}
