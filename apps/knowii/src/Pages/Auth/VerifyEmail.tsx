import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import AuthenticationCard from '@/Components/AuthenticationCard';
import { useRoute } from 'ziggy-js';
import { EMAIL_VERIFICATION_STATUS_LINK_SENT, EMAIL_VERIFICATION_URL, LOGOUT_URL, USER_PROFILE_URL } from '@knowii/common';
import Footer from '@/Components/Footer';
import { Button } from '@/Components/ui';

interface Props {
  status: string;
}

export default function VerifyEmailPage(props: Props) {
  const route = useRoute();

  const verificationLinkSent = props.status === EMAIL_VERIFICATION_STATUS_LINK_SENT;

  const form = useForm({});

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    form.post(route(EMAIL_VERIFICATION_URL));
  };

  return (
    <>
      <Head title="Email Verification" />
      <AuthenticationCard>
        <div className="mb-4 text-sm text-muted">
          Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the
          email, we will gladly send you another.
        </div>

        {verificationLinkSent && (
          <div role="status" className="mb-4 text-sm font-medium text-success">
            A new verification link has been sent to the email address you provided during registration.
          </div>
        )}

        <form onSubmit={submit}>
          <div className="mt-4 flex flex-col sm:flex-row items-center sm:justify-between">
            <Button type="submit" className="mb-4 sm:mb-0" loading={form.processing}>
              Resend verification email
            </Button>
            <div className="flex flex-col sm:flex-row items-center">
              <Link href={route(USER_PROFILE_URL)} className="simple-link touch-target mb-2 sm:mb-0 sm:mr-4">
                Edit profile
              </Link>
              <Link href={route(LOGOUT_URL)} method="post" className="simple-link touch-target" as="button">
                Log out
              </Link>
            </div>
          </div>
        </form>
      </AuthenticationCard>
      <Footer variant="compact" />
    </>
  );
}
