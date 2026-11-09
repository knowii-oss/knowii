import FooterGuest from '@/Components/FooterGuest';
import { Button, Checkbox, PasswordInput, TextInput } from '@/Components/ui';
import AuthenticationCard from '@/Components/AuthenticationCard';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { LOGIN_URL, PRIVACY_POLICY_URL, REGISTER_URL, TERMS_OF_SERVICE_URL, useTypedPage } from '@knowii/common';
import { useRoute } from 'ziggy-js';
import InputLabel from '@/Components/InputLabel';
import { FaAt, FaLock, FaUser } from 'react-icons/fa';
import InputError from '@/Components/InputError';

interface RegisterFormData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  terms: boolean;
}

export default function RegisterPage() {
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
    form.post(route(REGISTER_URL), {
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
            <TextInput
              icon={<FaUser />}
              size="lg"
              wrapperClassName="mt-1"
              id="name"
              type="text"
              value={form.data.name}
              onChange={(e) => form.setData('name', e.target.value)}
              autoComplete="name"
              autoFocus={true}
              required
              disabled={form.processing}
            />
            <InputError className="mt-2" message={form.errors.name} />
          </div>

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

          {/* Terms and privacy policy */}
          {page.props.jetstream.hasTermsAndPrivacyPolicyFeature && (
            <div className="mt-4">
              <div className="flex items-start gap-2">
                <Checkbox
                  name="terms"
                  id="terms"
                  className="mt-0.5"
                  checked={form.data.terms}
                  onChange={(e) => form.setData('terms', e.target.checked)}
                  required
                  disabled={form.processing}
                />
                <label htmlFor="terms" className="text-sm text-muted">
                  I agree to the{' '}
                  <a target="_blank" rel="noreferrer" href={route(TERMS_OF_SERVICE_URL)} className="simple-link">
                    Terms of Service
                  </a>{' '}
                  and{' '}
                  <a target="_blank" rel="noreferrer" href={route(PRIVACY_POLICY_URL)} className="simple-link">
                    Privacy Policy
                  </a>
                </label>
              </div>
              <InputError className="mt-2" message={form.errors.terms} />
            </div>
          )}

          {/* Actions */}
          <div className="mt-4 flex items-center justify-end">
            {/* Login */}
            <Link href={route(LOGIN_URL)} className="simple-link">
              Already registered?
            </Link>

            {/* Register */}
            <Button type="submit" className="ml-4" loading={form.processing}>
              Register
            </Button>
          </div>
        </form>
      </AuthenticationCard>
      <FooterGuest />
    </>
  );
}
