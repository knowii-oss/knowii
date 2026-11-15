import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import type { Community } from '@knowii/common';
import { knowiiApiClient, LOGIN_URL, REGISTER_URL, useTypedPage } from '@knowii/common';
import { FaDoorOpen } from 'react-icons/fa6';
import { Button, LinkButton, Spinner } from '@/Components/ui';

export interface JoinCommunityProps {
  community: Community;
}

/**
 * The way in of an open community (#1486, ADR 0044), for someone who is not a member: a signed-in person presses "Join",
 * a visitor gets "Sign up to join" and "Sign in" (the sign-up or the sign-in comes back to the page that they were
 * reading, where one click joins: nothing joins by itself). Shows nothing for a member, and for a community by invitation.
 *
 * The button stays enabled while it works (aria-busy and a guard): a disabled button loses the keyboard focus.
 */
export function JoinCommunity({ community }: JoinCommunityProps) {
  const user = useTypedPage().props.auth.user;
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // The state of a click that is in flight: a second click or Enter does nothing
  const running = useRef(false);

  if (community.is_member === true || community.join_policy !== 'open') {
    return null;
  }

  if (!user) {
    return (
      <div className="flex flex-wrap items-center gap-3">
        <LinkButton href={route(LOGIN_URL)} variant="secondary">
          Sign in
        </LinkButton>
        <LinkButton href={route(REGISTER_URL)}>Sign up to join</LinkButton>
      </div>
    );
  }

  const join = async () => {
    if (running.current) {
      return;
    }
    running.current = true;
    setBusy(true);
    setError(null);
    const result = await knowiiApiClient.communities.members.join(community.cuid);
    running.current = false;
    setBusy(false);
    if (!result.ok) {
      setError(result.message);
      return;
    }
    // The server answers the member view of the same address: the page loads again, with the spaces of the members
    router.reload();
  };

  return (
    <div className="flex flex-col items-start gap-2">
      <Button aria-busy={busy} aria-describedby={error ? `join-error-${community.cuid}` : undefined} onClick={() => void join()}>
        {busy ? <Spinner size="sm" label="Joining" /> : <FaDoorOpen aria-hidden="true" />}
        Join
      </Button>
      {error && (
        <p id={`join-error-${community.cuid}`} role="alert" className="text-sm text-danger-text">
          {error}
        </p>
      )}
    </div>
  );
}
