import Footer from '@/Components/Footer';
import AuthenticationCard from '@/Components/AuthenticationCard';
import { Button, Checkbox, InputError, InputLabel, PasswordInput, TextInput } from '@/Components/ui';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import {
  browserTimeZone,
  PRIVACY_POLICY_URL,
  REGISTER_LINK_STORE_URL,
  REGISTER_URL,
  TERMS_OF_SERVICE_URL,
  useTypedPage,
} from '@knowii/common';
import { useRoute } from 'ziggy-js';
import { FaLock, FaUser } from 'react-icons/fa';

interface FinishSignUpFormData {
  name: string;
  password: string;
  password_confirmation: string;
  terms: boolean;
  // The zone of the browser (#1476): the sign-up never fails when the browser gives none
  time_zone: string;
}

interface Props {
  token: string;
  valid: boolean;
  email: string | null;
  name: string | null;
}

/**
 * The page of the link of a sign-up (ADR 0065): the person chooses the password, and the button makes the account. Mail
 * scanners open links, so the page itself uses nothing up.
 */
export default function FinishSignUpPage({ token, valid, email, name }: Props) {
  const page = useTypedPage();
  const route = useRoute();

  const form = useForm<FinishSignUpFormData>({
    name: name ?? '',
    password: '',
    password_confirmation: '',
    terms: false,
    time_zone: browserTimeZone() ?? '',
  });
  // The box of the terms names only the legal texts that the instance has (#1653): both, one, or none
  const legalPages = page.props.legalPages ?? [];
  const hasTerms = legalPages.includes('terms-of-service');
  const hasPrivacyPolicy = legalPages.includes('privacy-policy');
  // A sign-up that did not work for a reason that the page does not name (the link is used up then)
  const notCreated = (form.errors as Record<string, string | undefined>)['email'];

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    form.post(route(REGISTER_LINK_STORE_URL, { token }), {
      onFinish: () => form.reset('password', 'password_confirmation'),
    });
  };

  return (
    <>
      <Head title="Finish your sign-up" />
      <AuthenticationCard>
        {valid ? (
          <form onSubmit={submit}>
            <h1 className="text-lg font-semibold">Finish your sign-up</h1>
            <p className="mt-2">
              Choose a password to create your account for <strong className="break-all">{email}</strong>.
            </p>

            {/* Name */}
            <div className="mt-4">
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
                required
                disabled={form.processing}
              />
              <InputError className="mt-2" message={form.errors.name} />
            </div>

            {/* The address, for password managers: it is the address of the link and cannot change here */}
            <input type="hidden" name="email" autoComplete="username" value={email ?? ''} />

            {/* Password */}
            <div className="mt-4">
              <InputLabel htmlFor="password">Password</InputLabel>
              <PasswordInput
                icon={<FaLock />}
                size="lg"
                wrapperClassName="mt-1"
                showStrength
                id="password"
                name="password"
                value={form.data.password}
                onChange={(e) => form.setData('password', e.target.value)}
                autoComplete="new-password"
                autoFocus={true}
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
                name="password_confirmation"
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
                    {/* 16 is the minimum age of the terms. No date of birth: the checkbox is enough (#1350). The sentence
                        names the texts that exist: without one, only the age stays (#1653) */}
                    I am 16 or older
                    {(hasTerms || hasPrivacyPolicy) && ' and I agree to the '}
                    {hasTerms && (
                      <a target="_blank" rel="noreferrer" href={route(TERMS_OF_SERVICE_URL)} className="simple-link">
                        Terms of Service
                      </a>
                    )}
                    {hasTerms && hasPrivacyPolicy && ' and '}
                    {hasPrivacyPolicy && (
                      <a target="_blank" rel="noreferrer" href={route(PRIVACY_POLICY_URL)} className="simple-link">
                        Privacy Policy
                      </a>
                    )}
                  </label>
                </div>
                <InputError className="mt-2" message={form.errors.terms} />
              </div>
            )}

            {/* The zone of the browser, in a hidden field (#1476): nothing is shown, and nothing fails when it is empty */}
            <input type="hidden" name="time_zone" value={form.data.time_zone} />

            <div className="mt-4 flex items-center justify-end">
              <Button type="submit" loading={form.processing}>
                Create my account
              </Button>
            </div>
          </form>
        ) : (
          <div className="flex flex-col gap-4">
            {notCreated ? <p className="text-danger-text">{notCreated}</p> : <p>This link expired or was already used. Sign up again.</p>}
            <Link href={route(REGISTER_URL)} className="simple-link touch-target">
              Sign up
            </Link>
          </div>
        )}
      </AuthenticationCard>
      <Footer variant="compact" />
    </>
  );
}
