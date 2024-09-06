import FooterGuest from '@/Components/FooterGuest';
import AuthenticationCard from '@/Components/AuthenticationCard';
import { Head, Link, useForm } from '@inertiajs/react';
import React, { FormEventHandler } from 'react';
import { LOGIN_URL, PRIVACY_POLICY_URL, TERMS_OF_SERVICE_URL, useTypedPage } from '@knowii/common';
import { useRoute } from 'ziggy-js';
import InputLabel from '@/Components/InputLabel';
import { FaAt, FaLock, FaUser } from 'react-icons/fa';
import { InputText } from 'primereact/inputtext';
import InputError from '@/Components/InputError';
import { Checkbox } from 'primereact/checkbox';
import { Button } from 'primereact/button';
import classNames from 'classnames';

interface RegisterFormData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  terms: boolean;
}

export default function Register() {
  const page = useTypedPage();
  const route = useRoute();

  const form = useForm<RegisterFormData>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    form.post(route('register'), {
      onFinish: () => form.reset('password', 'password_confirmation'),
    });
  };

  return (
    <>
      <Head title="Register" />
      <AuthenticationCard>
        <form onSubmit={submit}>
          {/* Name */}
          <div>
            <InputLabel htmlFor="name">Name</InputLabel>
            <div className="p-inputgroup mt-1">
              <span className="p-inputgroup-addon mt-1">
                <FaUser />
              </span>
              <InputText
                id="name"
                type="text"
                className="mt-1 block w-full p-inputtext-lg"
                value={form.data.name}
                onChange={(e) => form.setData('name', e.target.value)}
                autoComplete="name"
                autoFocus={true}
                required
              />
            </div>
            <InputError className="mt-2" message={form.errors.name} />
          </div>

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

          {/* Terms and privacy policy */}
          {page.props.jetstream.hasTermsAndPrivacyPolicyFeature && (
            <div className="mt-4">
              <InputLabel htmlFor="terms">
                <div className="flex items-center">
                  <Checkbox name="terms" id="terms" checked={form.data.terms} onChange={(e) => form.setData('terms', e.checked)} required />

                  <div className="ml-2">
                    I agree to the&nbsp;
                    <a target="_blank" href={route(TERMS_OF_SERVICE_URL)} className="simple-link">
                      Terms of Service&nbsp;
                    </a>
                    and&nbsp;
                    <a target="_blank" href={route(PRIVACY_POLICY_URL)} className="simple-link">
                      Privacy Policy
                    </a>
                  </div>
                </div>
                <InputError className="mt-2" message={form.errors.terms} />
              </InputLabel>
            </div>
          )}

          {/* Actions */}
          <div className="mt-4 flex items-center justify-end">
            {/* Login */}
            <Link href={route(LOGIN_URL)} className="simple-link">
              Already registered?
            </Link>

            {/* Register */}
            <Button label="Register" className={classNames('ml-4', { 'opacity-25': form.processing })} disabled={form.processing} />
          </div>
        </form>
      </AuthenticationCard>
      <FooterGuest />
    </>
  );
}
