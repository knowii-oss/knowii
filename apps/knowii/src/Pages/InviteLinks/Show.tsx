import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import type { InviteLinkPageProps } from '@knowii/common';
import { COMMUNITY_URL, INVITE_LINK_JOIN_URL, LOGIN_URL, REGISTER_URL, useAppData, useTypedPage } from '@knowii/common';
import { FaDoorOpen, FaLinkSlash } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import { INVITE_LINK_WORDS } from '@/Components/InviteLinks/invite-link-words';
import { Button, LinkButton, NoAccessPanel, Spinner } from '@/Components/ui';

// The links and the button of the next step: 44 px high on a phone (WCAG 2.5.8)
const NEXT_STEP_TARGET = 'touch-target';

/**
 * The page of an invite link (#1391, ADR 0062). Neither its address nor its props hold the token: the server keeps the
 * link of this browser in the session. The page shows the community and what the person can do: a visitor signs up or
 * signs in and comes back here, a signed-in person presses "Join" (nothing joins by itself), a member opens the community,
 * a person whom a manager removed reads why they cannot join. A link that does not work shows one text and nothing of a
 * community. The page never shows the name of the link, what it gives or how many people joined.
 *
 * "Join" is a POST to the page (`invite-links.join`) with the cuid of the community of the page: when another tab opened a
 * newer link, the server refuses and the page shows the newer link with the reason. A refusal comes back in the errors
 * of the page.
 */
export default function InviteLinkPage({ community, state }: InviteLinkPageProps) {
  const { props } = useTypedPage();
  const isVisitor = !props.auth.user;
  // The refusal of the last "Join" (InviteLinkController::ERROR_KEY)
  const joinError: unknown = props.errors?.join;
  const error = typeof joinError === 'string' ? joinError : null;
  const { showToast } = useAppData();
  const [busy, setBusy] = useState(false);
  // A click that is in flight: a second click or Enter does nothing
  const running = useRef(false);

  if (state === 'unusable' || community === null) {
    return (
      <AppLayout browserPageTitle={INVITE_LINK_WORDS.unusableTitle} breadcrumbHome={breadcrumbHome()} contentWidth="form">
        <NoAccessPanel
          icon={<FaLinkSlash />}
          title={INVITE_LINK_WORDS.unusableTitle}
          reason={INVITE_LINK_WORDS.unusableMessage}
          className="mx-auto mt-6 max-w-3xl"
        />
      </AppLayout>
    );
  }

  const join = () => {
    if (running.current) {
      return;
    }
    running.current = true;
    setBusy(true);
    router.post(
      route(INVITE_LINK_JOIN_URL),
      { community: community.cuid },
      {
        preserveScroll: true,
        onSuccess: () => showToast({ severity: 'success', summary: INVITE_LINK_WORDS.welcome(community.name) }),
        onFinish: () => {
          running.current = false;
          setBusy(false);
        },
      },
    );
  };

  const title = INVITE_LINK_WORDS.pageTitle(community.name);

  return (
    <AppLayout browserPageTitle={title} breadcrumbHome={breadcrumbHome()} contentWidth="form">
      <section aria-labelledby="invite-link-title" className="mx-auto mt-6 flex max-w-3xl flex-col gap-4">
        <h1 id="invite-link-title" className="text-2xl font-extrabold tracking-tight wrap-anywhere sm:text-3xl">
          {title}
        </h1>
        {/* Plain text: the description of a community is never Markdown */}
        {community.description && <p className="text-base whitespace-pre-line text-muted">{community.description}</p>}

        {isVisitor && (
          <>
            <div className="flex flex-wrap items-center gap-3">
              <LinkButton href={route(REGISTER_URL)} className={NEXT_STEP_TARGET}>
                {INVITE_LINK_WORDS.signUpToJoin}
              </LinkButton>
              <LinkButton href={route(LOGIN_URL)} variant="secondary" className={NEXT_STEP_TARGET}>
                {INVITE_LINK_WORDS.signIn}
              </LinkButton>
            </div>
            <p className="text-sm text-muted">{INVITE_LINK_WORDS.visitorHint}</p>
          </>
        )}

        {!isVisitor && state === 'join' && (
          <div className="flex flex-col items-start gap-2">
            <Button
              aria-busy={busy}
              aria-describedby={error ? 'invite-link-join-error' : undefined}
              onClick={join}
              className={NEXT_STEP_TARGET}
            >
              {busy ? <Spinner size="sm" label={INVITE_LINK_WORDS.joining} /> : <FaDoorOpen aria-hidden="true" />}
              {INVITE_LINK_WORDS.join}
            </Button>
            {error && (
              <p id="invite-link-join-error" role="alert" className="text-sm text-danger-text">
                {error}
              </p>
            )}
          </div>
        )}

        {!isVisitor && state === 'member' && (
          <div className="flex flex-col items-start gap-3">
            <p className="text-base">{INVITE_LINK_WORDS.alreadyMember}</p>
            <LinkButton href={route(COMMUNITY_URL, { communitySlug: community.slug })} className={NEXT_STEP_TARGET}>
              {INVITE_LINK_WORDS.openCommunity}
            </LinkButton>
          </div>
        )}

        {!isVisitor && state === 'blocked' && <p className="text-base">{INVITE_LINK_WORDS.blocked}</p>}
      </section>
    </AppLayout>
  );
}
