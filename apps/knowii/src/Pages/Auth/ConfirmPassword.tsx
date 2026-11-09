import FooterGuest from '@/Components/FooterGuest';
import { Button, PasswordInput } from '@/Components/ui';
import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { useRoute } from 'ziggy-js';
import { PASSWORD_CONFIRM_URL } from '@knowii/common';
import AuthenticationCard from '@/Components/AuthenticationCard';
import InputLabel from '@/Components/InputLabel';
import { FaLock } from 'react-icons/fa';
import InputError from '@/Components/InputError';

interface ConfirmPasswordFormData {
  password: string;
}

export default function ConfirmPasswordPage() {
  const route = useRoute();
  const form = useForm<ConfirmPasswordFormData>({
    password: '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    form.post(route(PASSWORD_CONFIRM_URL), {
      onFinish: () => form.reset(),
    });
  };

  return (
    <>
      <Head title="Confirm Password" />

      <AuthenticationCard>
        <div className="mb-4 text-sm text-muted">
          This is a secure area of the application. Please confirm your password before continuing.
        </div>

        <form onSubmit={submit}>
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
          <div className="mt-4 flex items-center justify-end">
            <Button type="submit" className="ml-4" loading={form.processing}>
              Confirm
            </Button>
          </div>
        </form>
      </AuthenticationCard>
      <FooterGuest />
    </>
  );
}
