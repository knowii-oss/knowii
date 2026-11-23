import { useEffect, useRef, useState } from 'react';
import type { EmailStatus } from '@knowii/common';
import { knowiiApiClient, USER_PROFILE_URL } from '@knowii/common';
import { Button, LinkButton } from '@/Components/ui';
import { EMAIL_STATUS_WORDS } from '@/Components/Notifications/email-status-words';

const HTTP_TOO_MANY_REQUESTS = 429;

/**
 * The box of a suppressed address on the notification settings page (#1478, ADR 0054, design 5.9 point 9): after a report
 * of the mail provider, the notification emails to the address of the member stopped. It reads the email status through
 * the API, and shows nothing while the address is not suppressed. "Try this address again" lifts the suppression through
 * the API (once an hour); "Change my address" opens the profile. The result goes through a status region; an error is an
 * alert, and the focus stays on the button (a busy button keeps it).
 */
export function EmailStatusBox() {
  const [status, setStatus] = useState<EmailStatus | null>(null);
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState('');
  const [error, setError] = useState('');
  // A call that is in flight: a second click or Enter does nothing
  const running = useRef(false);

  useEffect(() => {
    const controller = new AbortController();
    void knowiiApiClient.notifications.emailStatus(controller.signal).then((answer) => {
      if (answer.ok) {
        setStatus(answer.data);
      }
    });
    return () => controller.abort();
  }, []);

  if (status === null || !status.suppressed) {
    return null;
  }

  const tryAgain = async () => {
    if (running.current) {
      return;
    }
    running.current = true;
    setBusy(true);
    setResult('');
    setError('');
    const answer = await knowiiApiClient.notifications.tryAddressAgain();
    running.current = false;
    setBusy(false);
    if (answer.ok) {
      setResult(EMAIL_STATUS_WORDS.triedAgain);
    } else {
      setError(answer.status === HTTP_TOO_MANY_REQUESTS ? EMAIL_STATUS_WORDS.tooSoon : answer.message);
    }
  };

  return (
    <section
      aria-labelledby="email-status-title"
      className="mb-8 flex flex-col gap-3 rounded-lg border-2 border-warning bg-surface-elevated p-4 wrap-anywhere"
    >
      <h2 id="email-status-title" className="text-lg font-bold">
        {EMAIL_STATUS_WORDS.heading(status.address_hint)}
      </h2>
      <p className="text-sm">{status.reason === 'complaint' ? EMAIL_STATUS_WORDS.complaint : EMAIL_STATUS_WORDS.bounced}</p>
      <div className="flex flex-wrap items-center gap-3">
        <Button variant="primary" loading={busy} onClick={() => void tryAgain()}>
          {EMAIL_STATUS_WORDS.tryAgain}
        </Button>
        <LinkButton href={route(USER_PROFILE_URL)} variant="secondary" className="touch-target">
          {EMAIL_STATUS_WORDS.changeAddress}
        </LinkButton>
      </div>
      <p role="status" className={result ? 'text-sm font-bold' : 'sr-only'}>
        {result}
      </p>
      {error && (
        <p role="alert" className="text-sm font-bold text-danger-text">
          {error}
        </p>
      )}
    </section>
  );
}
