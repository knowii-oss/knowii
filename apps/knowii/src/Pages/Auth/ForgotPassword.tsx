import FooterGuest from '@/Components/FooterGuest';
import { Button, TextInput } from '@/Components/ui';
import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { useRoute } from 'ziggy-js';
import { EMAIL_PASSWORD_URL } from '@knowii/common';
import AuthenticationCard from '@/Components/AuthenticationCard';
import InputLabel from '@/Components/InputLabel';
import { FaAt } from 'react-icons/fa';
import InputError from '@/Components/InputError';

interface Props {
  status?: string;
}

interface ForgotPasswordFormData {
  email: string;
}

export default function ForgotPasswordPage(props: Props) {
  const route = useRoute();
  const form = useForm<ForgotPasswordFormData>({
    email: '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    form.post(route(EMAIL_PASSWORD_URL));
  };

  return (
    <>
      <Head title="Forgot Password" />
      <AuthenticationCard>
        <div className="mb-4 text-sm text-muted">
          Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow
          you to choose a new one.
        </div>

        {props.status && (
          <div role="status" className="mb-4 text-sm font-medium text-success">
            {props.status}
          </div>
        )}

        <form onSubmit={submit}>
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
            />
            <InputError className="mt-2" message={form.errors.email} />
          </div>
          <div className="mt-4 flex items-center justify-end">
            <Button type="submit" className="ml-4" loading={form.processing}>
              Email password reset link
            </Button>
          </div>
        </form>
      </AuthenticationCard>
      <FooterGuest />
    </>
  );
}
