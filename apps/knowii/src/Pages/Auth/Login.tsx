import { Link, useForm, Head } from '@inertiajs/react';
import classNames from 'classnames';
import { FormEventHandler } from 'react';
import AuthenticationCard from '@/Components/AuthenticationCard';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { useRoute } from 'ziggy-js';
import { FORGOT_PASSWORD_URL, LOGIN_URL, REGISTER_URL } from '@knowii/common';
import { InputText } from 'primereact/inputtext';
import { Button } from 'primereact/button';
import { FaAt, FaLock } from 'react-icons/fa';
import { Checkbox } from 'primereact/checkbox';
import FooterGuest from '@/Components/FooterGuest';

interface LoginFormData {
  email: string;
  password: string;
  remember: string;
}

interface Props {
  canResetPassword: boolean;
  status: string;
}

export default function Login({ canResetPassword, status }: Props) {
  const route = useRoute();

  const form = useForm<LoginFormData>({
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
      <Head title="Log in" />
      <AuthenticationCard>
        {status && <div className="mb-4 font-medium text-sm text-green-600">{status}</div>}

        <form onSubmit={submit}>
          {/* Email */}
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
                required
                autoComplete="current-password"
              />
            </div>
            <InputError className="mt-2" message={form.errors.password} />
          </div>

          {/* Remember me */}
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

          {/* Actions */}
          <div className="flex flex-col space-y-2 md:flex-row md:items-center md:justify-between md:space-y-0 mt-4">
            {/* Password reset */}
            {canResetPassword && (
              <div>
                <Link href={route(FORGOT_PASSWORD_URL)} className="simple-link">
                  Forgot your password?
                </Link>
              </div>
            )}

            <div className="flex items-center justify-end">
              {/* Register */}
              <Link href={route(REGISTER_URL)} className="simple-link">
                Need an account?
              </Link>

              {/* Log in */}
              <Button label="Log in" className={classNames('ml-4', { 'opacity-25': form.processing })} disabled={form.processing} />
            </div>
          </div>
        </form>
      </AuthenticationCard>
      <FooterGuest />
    </>
  );
}
