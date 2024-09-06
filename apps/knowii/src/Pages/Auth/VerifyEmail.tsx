import { Head, Link, useForm } from '@inertiajs/react';
import classNames from 'classnames';
import { FormEventHandler } from 'react';
import AuthenticationCard from '@/Components/AuthenticationCard';
import { useRoute } from 'ziggy-js';
import { EMAIL_VERIFICATION_STATUS_LINK_SENT, EMAIL_VERIFICATION_URL, LOGOUT_URL, USER_PROFILE_URL } from '@knowii/common';
import { Button } from 'primereact/button';
import FooterGuest from '@/Components/FooterGuest';

interface Props {
  status: string;
}

export default function Login(props: Props) {
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
        <div className="mb-4 text-sm text-gray-600">
          Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the
          email, we will gladly send you another.
        </div>

        {verificationLinkSent && (
          <div className="mb-4 font-medium text-sm text-green-600">
            A new verification link has been sent to the email address you provided during registration.
          </div>
        )}

        <form onSubmit={submit}>
          <div className="mt-4 flex items-center justify-between">
            <Button
              label="Resend verification email"
              className={classNames('ml-4', { 'opacity-25': form.processing })}
              disabled={form.processing}
            />
            <div>
              <Link href={route(USER_PROFILE_URL)} className="simple-link">
                Edit profile
              </Link>
            </div>
            <div>
              <Link href={route(LOGOUT_URL)} method="POST" className="simple-link">
                Log out
              </Link>
            </div>
          </div>
        </form>
      </AuthenticationCard>
      <FooterGuest />
    </>
  );
}
