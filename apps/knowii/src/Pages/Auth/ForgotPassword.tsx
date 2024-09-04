import FooterGuest from '@/Components/FooterGuest';
import { Head, useForm } from '@inertiajs/react';
import React, { FormEventHandler } from 'react';
import { useRoute } from 'ziggy-js';
import { EMAIL_PASSWORD_URL } from '@knowii/common';
import AuthenticationCard from '@/Components/AuthenticationCard';
import InputLabel from '@/Components/InputLabel';
import { FaAt } from 'react-icons/fa';
import { InputText } from 'primereact/inputtext';
import InputError from '@/Components/InputError';
import { Button } from 'primereact/button';
import classNames from 'classnames';

interface Props {
  status?: string;
}

interface ForgotPasswordFormData {
  email: string;
}

export default function ForgotPassword(props: Props) {
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
        <div className="mb-4 text-sm text-gray-600">
          Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow
          you to choose a new one.
        </div>

        {props.status && <div className="mb-4 font-medium text-sm text-green-600">{props.status}</div>}

        <form onSubmit={submit}>
          <div>
            <InputLabel htmlFor="email">Email</InputLabel>
            <div className="p-inputgroup mt-1">
              <span className="p-inputgroup-addon mt-1">
                <FaAt />
              </span>
              <InputText
                id="email"
                type="email"
                className="mt-1 block w-full p-inputtext-lg"
                value={form.data.email}
                onChange={(e) => form.setData('email', e.target.value)}
                autoComplete="username"
                autoFocus={true}
                required
              />
            </div>
            <InputError className="mt-2" message={form.errors.email} />
          </div>
          <div className="mt-4 flex items-center justify-end">
            <Button
              label="Email password reset link"
              className={classNames('ml-4', { 'opacity-25': form.processing })}
              disabled={form.processing}
            />
          </div>
        </form>
      </AuthenticationCard>
      <FooterGuest />
    </>
  );
}
