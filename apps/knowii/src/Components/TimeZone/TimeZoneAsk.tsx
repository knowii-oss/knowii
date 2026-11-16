import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { browserTimeZone, knowiiApiClient, useTypedPage } from '@knowii/common';
import { Button } from '@/Components/ui';

// One write without a click per page load: the account has no zone, so the zone of the browser is saved one time
let savedWithoutQuestion = false;

/**
 * For the tests: the module forgets that it saved
 */
export function resetTimeZoneAsk(): void {
  savedWithoutQuestion = false;
}

const answerKey = (userCuid: string, accountZone: string, browserZone: string) =>
  `knowii:time-zone-answer:${userCuid}:${accountZone}:${browserZone}`;

// The answer is remembered for that pair of zones, per account, in the storage of the browser. Without storage it is asked again.
function readAnswer(key: string): boolean {
  try {
    return window.localStorage.getItem(key) !== null;
  } catch {
    return false;
  }
}
function writeAnswer(key: string): void {
  try {
    window.localStorage.setItem(key, '1');
  } catch {
    // No storage: the question comes back at the next page load
  }
}

/**
 * The time zone of the account and the zone of the browser (#1476, ADR 0048, design 6.7 and 9.9), for a signed-in member:
 *
 * 1. The account has no zone: the zone of the browser is saved one time, without a question. The only write without a click.
 * 2. The browser reports another zone than the account: one plain region at the top of the page asks "Show times in <zone>?"
 *    once per new zone. It is not a dialog and never takes the focus. The platform never changes the zone alone.
 *
 * It renders nothing for a visitor and when the browser gives no zone.
 */
export function TimeZoneAsk() {
  const page = useTypedPage();
  const user = page.props.auth?.user ?? null;
  const accountZone = user?.time_zone;
  const browserZone = browserTimeZone();
  const userCuid = user?.cuid ?? '';
  const [answered, setAnswered] = useState<ReadonlySet<string>>(new Set());
  const [busy, setBusy] = useState(false);
  const [failed, setFailed] = useState(false);
  const busyRef = useRef(false);

  // `null` is "not known". `undefined` is an old session without the field: nothing to do.
  const needsSave = userCuid !== '' && accountZone === null && browserZone !== null;
  useEffect(() => {
    if (!needsSave || savedWithoutQuestion || browserZone === null) {
      return;
    }
    savedWithoutQuestion = true;
    void knowiiApiClient.users.setTimeZone(browserZone).then((result) => {
      if (result.ok) {
        router.reload({ only: ['auth'] });
      }
    });
  }, [needsSave, browserZone]);

  if (!user || !accountZone || browserZone === null || accountZone === browserZone) {
    return null;
  }
  const key = answerKey(userCuid, accountZone, browserZone);
  if (answered.has(key) || readAnswer(key)) {
    return null;
  }

  const answer = (next: ReadonlySet<string> = new Set([...answered, key])) => {
    writeAnswer(key);
    setAnswered(next);
  };

  const use = async () => {
    if (busyRef.current) {
      return;
    }
    busyRef.current = true;
    setBusy(true);
    setFailed(false);
    const result = await knowiiApiClient.users.setTimeZone(browserZone);
    busyRef.current = false;
    setBusy(false);
    if (!result.ok) {
      setFailed(true);
      return;
    }
    answer();
    router.reload({ only: ['auth'] });
  };

  return (
    <div className="border-b border-border bg-surface">
      <div role="region" aria-labelledby="time-zone-question" className="page-container flex flex-wrap items-center gap-x-4 gap-y-2 py-2">
        <p id="time-zone-question" className="text-sm font-bold">
          Show times in {browserZone}?
        </p>
        <div className="flex flex-wrap gap-2">
          <Button size="sm" aria-disabled={busy || undefined} aria-busy={busy || undefined} onClick={() => void use()}>
            Yes, use {browserZone}
          </Button>
          <Button size="sm" variant="secondary" onClick={() => answer()}>
            Keep {accountZone}
          </Button>
        </div>
        {failed && (
          <p role="alert" className="text-sm text-danger-text">
            Not saved. Retry.
          </p>
        )}
      </div>
    </div>
  );
}
