import type { MouseEvent } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import type { CatchUp, CatchUpLine, Community } from '@knowii/common';
import { catchUpLineDetail, catchUpLineHeadline, knowiiApiClient, NOTIFICATIONS_URL, useTypedPage } from '@knowii/common';
import { useRoute } from 'ziggy-js';
import { Button, RelativeTime } from '@/Components/ui';
import { clearCatchUp, useActiveCatchUp } from './catch-up-state';
import { notificationHref } from './notification-link';
import { useNotificationsOptional } from './NotificationsProvider';

export type CatchUpCommunity = Pick<Community, 'cuid' | 'slug' | 'name'>;

export interface CatchUpCardProps {
  community: CatchUpCommunity;
  // The level of the heading "Catch up": 2 on the home and on the page "Notifications", 3 in the panel
  headingLevel?: 2 | 3;
  // Name the community under the heading (the page "Notifications" has one card per community)
  showCommunity?: boolean;
  /**
   * "Show all": the home card opens the panel (the default). In the panel and on the page it moves the focus to the heading
   * "Needs you": give the function that does it.
   */
  onShowAll?: () => void;
  /**
   * The card was cleared: the caller clears what else the button clears (the lines of "Following") and moves the focus to the
   * heading of the list under the card. Without it the focus goes to the main heading of the page.
   */
  onCaughtUp?: () => void;
}

// The status text stays a few seconds
const STATUS_MS = 4000;

type CardState = { status: 'loading' } | { status: 'error' } | { status: 'ready'; data: CatchUp };

function lineKey(line: CatchUpLine, index: number): string {
  return `${line.kind}-${line.target.post_cuid ?? line.target.chat_message_cuid ?? line.target.resource_cuid ?? line.target.page_slug ?? ''}-${index}`;
}

/**
 * The "Catch up" card (#1476, design 5.5 and 9.7): what a member missed after a week away, in at most 5 lines of 2 lines of
 * text, each one a link. It shows only while the shared prop `catchUp` holds a date and the community has lines (nothing
 * until then, no empty card, and no count of what was missed). It is a region with the heading "Catch up": not a dialog and
 * not a pop-up. "I am caught up" hides it everywhere and clears the lines of "Following".
 */
export function CatchUpCard({ community, headingLevel = 2, showCommunity = false, onShowAll, onCaughtUp }: CatchUpCardProps) {
  const page = useTypedPage();
  const route = useRoute();
  const notifications = useNotificationsOptional();
  const since = useActiveCatchUp(page.props.catchUp?.since);
  const [state, setState] = useState<{ key: string; value: CardState }>({ key: '', value: { status: 'loading' } });
  const [busy, setBusy] = useState(false);
  const [failed, setFailed] = useState(false);
  const [status, setStatus] = useState('');
  const busyRef = useRef(false);
  const statusTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const headingId = useId();
  const communityId = useId();
  const Heading = headingLevel === 2 ? 'h2' : 'h3';

  const requestKey = since ? `${community.cuid}:${since}` : '';
  useEffect(() => {
    if (!since) {
      return;
    }
    const controller = new AbortController();
    void knowiiApiClient.notifications.catchUp(community.cuid, since, controller.signal).then((result) => {
      if (controller.signal.aborted) {
        return;
      }
      setState({ key: requestKey, value: result.ok ? { status: 'ready', data: result.data } : { status: 'error' } });
    });
    return () => controller.abort();
  }, [community.cuid, since, requestKey]);

  useEffect(
    () => () => {
      if (statusTimer.current) {
        clearTimeout(statusTimer.current);
      }
    },
    [],
  );

  const current = state.key === requestKey ? state.value : null;
  const lines = current?.status === 'ready' ? current.data.lines : [];

  const showAll = (event: MouseEvent) => {
    event.preventDefault();
    if (onShowAll) {
      onShowAll();
    } else {
      notifications?.openPanel();
    }
  };

  const caughtUp = async () => {
    if (busyRef.current || !since) {
      return;
    }
    busyRef.current = true;
    setBusy(true);
    setFailed(false);
    const result = await knowiiApiClient.notifications.caughtUp();
    busyRef.current = false;
    setBusy(false);
    if (!result.ok) {
      setFailed(true);
      return;
    }
    // The card goes everywhere; the status text stays here, outside the region that went
    clearCatchUp(since);
    notifications?.refreshCount();
    setStatus('You are caught up');
    statusTimer.current = setTimeout(() => {
      statusTimer.current = null;
      setStatus('');
    }, STATUS_MS);
    if (onCaughtUp) {
      onCaughtUp();
    } else {
      // No list under the card: the focus goes to the main heading of the page
      const main = document.querySelector<HTMLElement>('h1');
      if (main) {
        main.tabIndex = -1;
        main.focus();
      }
    }
  };

  return (
    <>
      {since && lines.length > 0 && (
        <section
          aria-labelledby={showCommunity ? `${headingId} ${communityId}` : headingId}
          data-testid="catch-up-card"
          className="flex flex-col gap-3 rounded-xl border border-border bg-surface-elevated p-4"
        >
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="min-w-0">
              <Heading id={headingId} className="text-base font-extrabold">
                Catch up
              </Heading>
              {showCommunity && (
                <p id={communityId} className="text-xs text-muted">
                  {community.name}
                </p>
              )}
            </div>
            <Button
              variant="secondary"
              size="sm"
              aria-disabled={busy || undefined}
              aria-busy={busy || undefined}
              onClick={() => void caughtUp()}
            >
              I am caught up
            </Button>
          </div>

          <ul className="flex flex-col">
            {lines.map((line, index) => {
              const href = notificationHref({ community, space: line.space, target: line.target }, route);
              const detail = catchUpLineDetail(line);
              const body = (
                <>
                  <span className="block truncate text-sm font-bold text-primary">{catchUpLineHeadline(line)}</span>
                  <span className="block truncate text-sm text-muted">
                    {detail ?? (
                      <>
                        {line.space ? `${line.space.name}, ` : ''}
                        <RelativeTime date={line.date} />
                      </>
                    )}
                  </span>
                </>
              );
              return (
                <li key={lineKey(line, index)}>
                  {href ? (
                    <Link
                      href={href}
                      className="block rounded px-2 py-2 hover:bg-overlay/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    >
                      {body}
                    </Link>
                  ) : (
                    <div className="px-2 py-2">{body}</div>
                  )}
                </li>
              );
            })}
          </ul>

          {current?.status === 'ready' && current.data.has_more && (
            <Link
              href={route(NOTIFICATIONS_URL, {})}
              onClick={showAll}
              className="self-start rounded text-sm font-bold underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-primary"
            >
              Show all
            </Link>
          )}

          {failed && (
            <p role="alert" className="text-sm text-danger-text">
              We could not clear it. Try again.
            </p>
          )}
        </section>
      )}
      {/* The status text outlives the card: the region is there while the card is, and says "You are caught up" (polite) */}
      {(since || status) && (
        <div role="status" aria-live="polite" className={status ? 'rounded-lg bg-overlay/10 px-3 py-2 text-sm font-bold' : 'sr-only'}>
          {status}
        </div>
      )}
    </>
  );
}
