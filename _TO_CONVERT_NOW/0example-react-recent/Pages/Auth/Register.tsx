import React, { useState, FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticationCard from '@/Components/AuthenticationCard';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { usePage } from '@inertiajs/react';

interface RegisterFormData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  terms: boolean;
}

const Register: React.FC = () => {
  const page: any = usePage();

  const { data, setData, post, processing, errors, reset } = useForm<RegisterFormData>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    post(route('register'), {
      onFinish: () => reset('password', 'password_confirmation'),
    });
  };

  return (
    <>
      <Head title="Register" />

      <AuthenticationCard logo={<AuthenticationCardLogo />}>
        <form onSubmit={submit}>
          <div>
            <InputLabel htmlFor="name" value="Name" />
            <TextInput
              id="name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              type="text"
              className="block w-full mt-1"
              required
              autoFocus
              autoComplete="name"
            />
            <InputError className="mt-2" message={errors.name} />
          </div>

          <div className="mt-4">
            <InputLabel htmlFor="email" value="Email" />
            <TextInput
              id="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              type="email"
              className="block w-full mt-1"
              required
              autoComplete="username"
            />
            <InputError className="mt-2" message={errors.email} />
          </div>

          <div className="mt-4">
            <InputLabel htmlFor="password" value="Password" />
            <TextInput
              id="password"
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
              type="password"
              className="block w-full mt-1"
              required
              autoComplete="new-password"
            />
            <InputError className="mt-2" message={errors.password} />
          </div>

          <div className="mt-4">
            <InputLabel htmlFor="password_confirmation" value="Confirm Password" />
            <TextInput
              id="password_confirmation"
              value={data.password_confirmation}
              onChange={(e) => setData('password_confirmation', e.target.value)}
              type="password"
              className="block w-full mt-1"
              required
              autoComplete="new-password"
            />
            <InputError className="mt-2" message={errors.password_confirmation} />
          </div>

          {page.props.jetstream.hasTermsAndPrivacyPolicyFeature && (
            <div className="mt-4">
              <InputLabel htmlFor="terms">
                <div className="flex items-center">
                  <Checkbox id="terms" checked={data.terms} onChange={(e) => setData('terms', e.target.checked)} name="terms" required />
                  <div className="ms-2">
                    I agree to the
                    <a
                      target="_blank"
                      href={route('terms.show')}
                      className="text-sm text-gray-600 underline rounded-md dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800"
                    >
                      Terms of Service
                    </a>
                    and
                    <a
                      target="_blank"
                      href={route('policy.show')}
                      className="text-sm text-gray-600 underline rounded-md dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800"
                    >
                      Privacy Policy
                    </a>
                  </div>
                </div>
                <InputError className="mt-2" message={errors.terms} />
              </InputLabel>
            </div>
          )}

          <div className="flex items-center justify-end mt-4">
            <Link
              href={route('login')}
              className="text-sm text-gray-600 underline rounded-md dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800"
            >
              Already registered?
            </Link>

            <PrimaryButton className={`ms-4 ${processing ? 'opacity-25' : ''}`} disabled={processing}>
              Register
            </PrimaryButton>
          </div>
        </form>
      </AuthenticationCard>
    </>
  );
};

export default Register;
