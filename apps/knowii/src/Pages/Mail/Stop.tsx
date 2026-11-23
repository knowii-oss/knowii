import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import type { NotificationEmailGroup } from '@knowii/common';
import { USER_NOTIFICATIONS_URL } from '@knowii/common';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import { MAIL_STOP_WORDS } from '@/Components/Mail/mail-stop-words';
import { Button, LinkButton } from '@/Components/ui';

export type MailStopState = 'confirm' | 'done' | 'undone' | 'invalid';

interface Props {
  // WARNING: aligned with MailStopController.php
  state: MailStopState;
  group: NotificationEmailGroup | null;
  // The signed addresses of the stop (the POST of the button) and of the undo, and the signed addresses of the page after
  // each one. Null in the states that do not use them
  stopUrl: string | null;
  doneUrl: string | null;
  undoUrl: string | null;
  undoneUrl: string | null;
}

/**
 * The page of the stop link of a notification email (#1478, ADR 0054, design 5.6 point 8 and 8.3 point 3). It opens
 * without a sign-in: the signature of the address is the proof. A GET changes nothing: the confirm state has one button.
 * The button sends the POST of the page (`source=page`), then the page shows what stopped and what still arrives, with
 * "Undo". The page names no person, no address and no community, because the link can be forwarded. The state is in text,
 * never only in a color or an icon. A failed POST shows an alert and keeps the focus on the button.
 */
export default function MailStop({ state, group, stopUrl, doneUrl, undoUrl, undoneUrl }: Props) {
  const [busy, setBusy] = useState(false);
  const [failed, setFailed] = useState(false);
  // A POST that is in flight: a second click or Enter does nothing
  const running = useRef(false);

  const post = async (url: string | null, next: string | null) => {
    if (running.current || url === null || next === null) {
      return;
    }
    running.current = true;
    setBusy(true);
    setFailed(false);
    let ok = false;
    try {
      // The answer of the page is a redirect to the next state: a manual redirect gives an opaque answer
      const response = await fetch(url, {
        method: 'POST',
        body: new URLSearchParams({ source: 'page' }),
        headers: { Accept: 'text/html' },
        credentials: 'same-origin',
        redirect: 'manual',
      });
      ok = response.type === 'opaqueredirect' || (response.status >= 200 && response.status < 400);
    } catch {
      ok = false;
    }
    running.current = false;
    setBusy(false);
    if (ok) {
      router.visit(next);
    } else {
      setFailed(true);
    }
  };

  if (state === 'invalid' || group === null) {
    return (
      <AppLayout browserPageTitle={MAIL_STOP_WORDS.invalidTitle} breadcrumbHome={breadcrumbHome()} contentWidth="form">
        <section aria-labelledby="mail-stop-title" className="mx-auto mt-6 flex max-w-3xl flex-col gap-4">
          <h1 id="mail-stop-title" className="text-2xl font-extrabold tracking-tight sm:text-3xl">
            {MAIL_STOP_WORDS.invalidTitle}
          </h1>
          <p className="text-base">{MAIL_STOP_WORDS.invalidText}</p>
          <div>
            <LinkButton href={route(USER_NOTIFICATIONS_URL)} variant="secondary" className="touch-target">
              {MAIL_STOP_WORDS.emailSettings}
            </LinkButton>
          </div>
        </section>
      </AppLayout>
    );
  }

  const title =
    state === 'confirm'
      ? MAIL_STOP_WORDS.confirmTitle(group)
      : state === 'done'
        ? MAIL_STOP_WORDS.doneTitle(group)
        : MAIL_STOP_WORDS.undoneTitle(group);

  return (
    <AppLayout browserPageTitle={title} breadcrumbHome={breadcrumbHome()} contentWidth="form">
      <section aria-labelledby="mail-stop-title" className="mx-auto mt-6 flex max-w-3xl flex-col gap-4">
        <h1 id="mail-stop-title" className="text-2xl font-extrabold tracking-tight sm:text-3xl">
          {title}
        </h1>

        {state === 'confirm' && (
          <>
            <p className="text-base">{MAIL_STOP_WORDS.confirmText}</p>
            <div>
              <Button variant="primary" loading={busy} onClick={() => void post(stopUrl, doneUrl)}>
                {MAIL_STOP_WORDS.confirmButton(group)}
              </Button>
            </div>
          </>
        )}

        {state === 'done' && (
          <>
            <p className="text-base">{MAIL_STOP_WORDS.doneEveryCommunity}</p>
            <p className="text-base">{MAIL_STOP_WORDS.doneStillArrives}</p>
            <div className="flex flex-wrap items-center gap-3">
              <Button variant="primary" loading={busy} onClick={() => void post(undoUrl, undoneUrl)}>
                {MAIL_STOP_WORDS.undo}
              </Button>
              <LinkButton href={route(USER_NOTIFICATIONS_URL)} variant="secondary" className="touch-target">
                {MAIL_STOP_WORDS.emailSettings}
              </LinkButton>
            </div>
          </>
        )}

        {state === 'undone' && (
          <>
            <p className="text-base">{MAIL_STOP_WORDS.undoneText}</p>
            <div>
              <LinkButton href={route(USER_NOTIFICATIONS_URL)} variant="secondary" className="touch-target">
                {MAIL_STOP_WORDS.emailSettings}
              </LinkButton>
            </div>
          </>
        )}

        {failed && (
          <p role="alert" className="text-sm font-bold text-danger-text">
            {MAIL_STOP_WORDS.failed}
          </p>
        )}
      </section>
    </AppLayout>
  );
}
