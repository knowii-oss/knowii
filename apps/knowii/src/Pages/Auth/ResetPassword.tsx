import FooterGuest from '@/Components/FooterGuest';
import AuthenticationCard from '@/Components/AuthenticationCard';
import { Head, useForm } from '@inertiajs/react';
import React, { FormEventHandler } from 'react';
import { PASSWORD_UPDATE_URL } from '@knowii/common';
import { useRoute } from 'ziggy-js';
import InputLabel from '@/Components/InputLabel';
import { FaAt, FaLock } from 'react-icons/fa';
import { InputText } from 'primereact/inputtext';
import InputError from '@/Components/InputError';
import { Button } from 'primereact/button';
import classNames from 'classnames';

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

export default function Register(props: Props) {
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
                required
              />
            </div>
            <InputError className="mt-2" message={form.errors.email} />
          </div>

          {/* Password */}
          <div className="mt-4">
            <InputLabel htmlFor="password">Password</InputLabel>
            <div className="p-inputgroup mt-1">
              <span className="p-inputgroup-addon mt-1">
                <FaLock />
              </span>
              <InputText
                id="password"
                type="password"
                className="mt-1 block w-full p-inputtext-lg"
                value={form.data.password}
                onChange={(e) => form.setData('password', e.target.value)}
                autoComplete="new-password"
                required
              />
            </div>
            <InputError className="mt-2" message={form.errors.password} />
          </div>

          {/* Confirm password */}
          <div className="mt-4">
            <InputLabel htmlFor="password_confirmation">Confirm password</InputLabel>
            <div className="p-inputgroup mt-1">
              <span className="p-inputgroup-addon mt-1">
                <FaLock />
              </span>
              <InputText
                id="password_confirmation"
                type="password"
                className="mt-1 block w-full p-inputtext-lg"
                value={form.data.password_confirmation}
                onChange={(e) => form.setData('password_confirmation', e.target.value)}
                autoComplete="new-password"
                required
              />
            </div>
            <InputError className="mt-2" message={form.errors.password_confirmation} />
          </div>

          {/* Actions */}
          <div className="mt-4 flex items-center justify-end">
            {/* Reset password */}
            <Button label="Reset password" className={classNames('ml-4', { 'opacity-25': form.processing })} disabled={form.processing} />
          </div>
        </form>
      </AuthenticationCard>
      <FooterGuest />
    </>
  );
}
