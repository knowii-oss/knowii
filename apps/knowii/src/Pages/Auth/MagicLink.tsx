import { Head, Link, useForm } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import { LOGIN_LINK_CONSUME_URL, LOGIN_URL } from '@knowii/common';
import AuthenticationCard from '@/Components/AuthenticationCard';
import FooterGuest from '@/Components/FooterGuest';
import { Button } from '@/Components/ui';

interface Props {
  token: string;
  valid: boolean;
  email: string | null;
}

/**
 * The page of a sign-in link (#703, ADR 0023): the member confirms with a button. Mail scanners open links, so the
 * link itself never signs in.
 */
export default function MagicLinkPage({ token, valid, email }: Props) {
  const route = useRoute();
  const form = useForm({});

  return (
    <>
      <Head title="Sign in with a link" />
      <AuthenticationCard>
        {valid ? (
          <form
            onSubmit={(event) => {
              event.preventDefault();
              form.post(route(LOGIN_LINK_CONSUME_URL, { token }));
            }}
            className="flex flex-col gap-4"
          >
            <p>
              Sign in to Knowii as <strong className="break-all">{email}</strong>?
            </p>
            <Button type="submit" loading={form.processing} autoFocus>
              Sign in
            </Button>
          </form>
        ) : (
          <div className="flex flex-col gap-4">
            <p>This sign-in link expired or was already used.</p>
            <Link href={route(LOGIN_URL)} className="simple-link touch-target">
              Ask for a new link
            </Link>
          </div>
        )}
      </AuthenticationCard>
      <FooterGuest />
    </>
  );
}
