import { Link, useForm, Head } from '@inertiajs/react';
import classNames from 'classnames';
import React, { FormEventHandler } from 'react';
import AuthenticationCard from '@/Components/AuthenticationCard';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { useRoute } from 'ziggy-js';
import { FORGOT_PASSWORD_URL, LOGIN_URL, REGISTER_URL } from '@knowii/common';
import { InputText } from 'primereact/inputtext';
import { Button } from 'primereact/button';
import { FaPassport, FaUser } from 'react-icons/fa';
import { Checkbox } from 'primereact/checkbox';

interface Props {
  canResetPassword: boolean;
  status: string;
}

export default function Login({ canResetPassword, status }: Props) {
  const route = useRoute();

  const form = useForm({
    email: '',
    password: '',
    remember: '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    form.post(route(LOGIN_URL), {
      onFinish: () => form.reset('password'),
    });
  };

  return (
    <>
      <AuthenticationCard>
        <Head title="Log in" />

        {status && <div className="mb-4 font-medium text-sm text-green-600">{status}</div>}

        <form onSubmit={submit}>
          <div>
            <InputLabel htmlFor="email">Email</InputLabel>
            <div className="p-inputgroup mt-1">
              <span className="p-inputgroup-addon mt-1">
                <FaUser />
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

          <div className="mt-4">
            <InputLabel htmlFor="password">Password</InputLabel>
            <div className="p-inputgroup mt-1">
              <span className="p-inputgroup-addon mt-1">
                <FaPassport />
              </span>
              <InputText
                id="password"
                type="password"
                className="mt-1 block w-full p-inputtext-lg"
                value={form.data.password}
                onChange={(e) => form.setData('password', e.target.value)}
                required
                autoComplete="current-password"
              />
            </div>
            <InputError className="mt-2" message={form.errors.password} />
          </div>

          <div className="mt-4">
            <label className="flex items-center">
              <Checkbox
                name="remember"
                checked={form.data.remember === 'on'}
                onChange={(e) => form.setData('remember', e.target.checked ? 'on' : '')}
              />
              <span className="ms-2 text-sm text-gray-600">Remember me</span>
            </label>
          </div>

          <div className="flex flex-col space-y-2 md:flex-row md:items-center md:justify-between md:space-y-0 mt-4">
            {canResetPassword && (
              <div>
                <Link
                  href={route(FORGOT_PASSWORD_URL)}
                  className="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Forgot your password?
                </Link>
              </div>
            )}

            <div className="flex items-center justify-end">
              <Link
                href={route(REGISTER_URL)}
                className="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                Need an account?
              </Link>

              <Button label="Log in" className={classNames('ml-4', { 'opacity-25': form.processing })} disabled={form.processing} />
            </div>
          </div>
        </form>
      </AuthenticationCard>
      <div className="mt-4 py-2 text-center text-gray-600 text-sm">
        <span>© Knowii. All rights reserved.</span>
      </div>
    </>
  );
}
