import type { MouseEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { FaEllipsis } from 'react-icons/fa6';
import type { Notification } from '@knowii/common';
import {
  formatFullDateTime,
  notificationReasonSentence,
  notificationSentence,
  notificationSentenceHasTitle,
  notificationSettingsLinkName,
} from '@knowii/common';
import {
  Avatar,
  Button,
  cn,
  DropdownMenu,
  DropdownMenuItem,
  dropdownMenuItemClasses,
  RelativeTime,
  useAccountTimeZone,
} from '@/Components/ui';
import { canStopFollowing, threadOf } from './notification-thread';

// The menu of a line closes with a short transition and then gives the focus back to its button
const WHY_FOCUS_DELAY_MS = 150;

export interface NotificationLineProps {
  notification: Notification;
  // The address of the exact item, null when it cannot be opened
  href: string | null;
  // The line was new when the list loaded: bold, with the word "New" (never by color alone)
  isNew: boolean;
  // The name of the community is shown when the member is in more than one
  showCommunity: boolean;
  // A button of the line was pressed and the server did not answer yet
  busy: boolean;
  // Another device marked it as done while the focus was on it: it stays until the focus leaves it
  doneElsewhere: boolean;
  // The setting behind the line: the notification settings page, at the space of the line
  settingsHref: string;
  onOpen: (event: MouseEvent, notification: Notification, href: string) => void;
  onDone: (notification: Notification) => void;
  onStopFollowing: (notification: Notification) => void;
  onMuteThread: (notification: Notification) => void;
  onMuteSpace: (notification: Notification) => void;
  onLeave: (notification: Notification) => void;
  // Callback refs: the panel finds the line and its link to move the focus and to see what is on screen
  registerLine: (element: HTMLLIElement | null) => void;
  registerLink: (element: HTMLAnchorElement | null) => void;
}

/**
 * A line of the Notifications panel (design 5.1): the avatar of the last author, one sentence, the title, a 2-line
 * excerpt, the space and the time. One link (the sentence, stretched over the line) and real buttons: "Done" on a
 * "Needs you" line, "Stop following" on a thread line of "Following", and a small menu on each line (#1497).
 */
export function NotificationLine(props: NotificationLineProps) {
  const { notification, href, isNew, showCommunity, busy, doneElsewhere, registerLine, registerLink } = props;
  const zone = useAccountTimeZone();
  const sentence = notificationSentence(notification);
  const where = [notification.space?.name, showCommunity ? notification.community.name : null].filter(Boolean).join(' · ');
  const label = `${sentence}${notification.title && !notificationSentenceHasTitle(notification) ? `: ${notification.title}` : ''}`;
  const avatar = notification.actor;
  const isFollowing = notification.level === 2;
  const thread = threadOf(notification);

  // "Why did I get this?": the reason, shown under the line, with the focus on it so that it is read
  const [whyOpen, setWhyOpen] = useState(false);
  const why = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!whyOpen) {
      return;
    }
    why.current?.focus();
    // The menu gives the focus back to its button when it closes, after this effect: the reason takes it again, unless the
    // member moved the focus to another place in the meantime
    const timer = setTimeout(() => {
      const active = document.activeElement;
      if (active === document.body || (active instanceof HTMLElement && active.getAttribute('aria-haspopup') === 'menu')) {
        why.current?.focus();
      }
    }, WHY_FOCUS_DELAY_MS);
    return () => clearTimeout(timer);
  }, [whyOpen]);

  const sentenceClasses = cn('text-sm', isNew ? 'font-bold' : 'font-medium');
  // The space after the word keeps "New" and the name apart in the accessible name of the link
  const newWord = isNew && (
    <>
      <span className="mr-1 inline-block rounded border border-primary px-1.5 py-px align-middle text-xs font-bold uppercase">
        New
      </span>{' '}
    </>
  );

  return (
    <li
      ref={registerLine}
      data-notification-cuid={notification.cuid}
      onBlur={(event) => {
        if (doneElsewhere && !event.currentTarget.contains(event.relatedTarget)) {
          props.onLeave(notification);
        }
      }}
      className="relative flex gap-3 rounded-lg px-2 py-3 focus-within:bg-overlay/5 hover:bg-overlay/5"
    >
      <Avatar name={avatar ? avatar.name : 'Deleted member'} imageUrl={avatar?.avatar_url ?? null} size="sm" />
      <div className="flex min-w-0 grow flex-col gap-1">
        {href ? (
          <Link
            ref={registerLink}
            href={href}
            onClick={(event) => props.onOpen(event, notification, href)}
            className={cn(
              sentenceClasses,
              'rounded text-primary after:absolute after:inset-0 after:content-[""] focus-visible:outline-2 focus-visible:outline-primary',
            )}
          >
            {newWord}
            {sentence}
          </Link>
        ) : (
          <p className={sentenceClasses}>
            {newWord}
            {sentence}
          </p>
        )}
        {notification.title && !notificationSentenceHasTitle(notification) && (
          <p className="truncate text-sm text-primary">{notification.title}</p>
        )}
        {notification.excerpt && <p className="line-clamp-2 text-sm break-words text-muted">{notification.excerpt}</p>}
        <p className="flex flex-wrap gap-x-2 text-xs text-muted">
          {where && <span>{where}</span>}
          <span>
            <RelativeTime date={notification.updated_at} />
            <span className="sr-only">, {formatFullDateTime(notification.updated_at, zone)}</span>
          </span>
        </p>
        {doneElsewhere && <p className="text-xs font-bold text-primary">Done on another device</p>}
        {whyOpen && (
          <div
            ref={why}
            id={`notification-why-${notification.cuid}`}
            tabIndex={-1}
            className="relative z-10 flex flex-col gap-1 rounded-lg border border-border bg-well px-3 py-2 text-sm outline-none focus-visible:outline-2 focus-visible:outline-primary"
          >
            <p>{notificationReasonSentence(notification)}</p>
            <Link
              href={props.settingsHref}
              className="w-fit rounded font-bold underline focus-visible:outline-2 focus-visible:outline-primary"
            >
              {notificationSettingsLinkName(notification)}
            </Link>
          </div>
        )}
      </div>
      {/* Above the stretched link; never `disabled` or `loading`: the focus stays on the button */}
      <div className="relative z-10 flex shrink-0 flex-col items-end gap-1 self-start">
        {!isFollowing && (
          <Button
            variant="secondary"
            size="sm"
            aria-label={`Done: ${label}`}
            aria-disabled={busy || doneElsewhere || undefined}
            aria-busy={busy || undefined}
            onClick={() => {
              if (!busy && !doneElsewhere) {
                props.onDone(notification);
              }
            }}
          >
            Done
          </Button>
        )}
        {canStopFollowing(notification) && (
          <Button
            variant="secondary"
            size="sm"
            aria-label={`Stop following: ${label}`}
            aria-disabled={busy || undefined}
            aria-busy={busy || undefined}
            onClick={() => {
              if (!busy) {
                props.onStopFollowing(notification);
              }
            }}
          >
            Stop following
          </Button>
        )}
        <DropdownMenu
          label={`More for: ${label}`}
          trigger={<FaEllipsis aria-hidden="true" className="size-4" />}
          triggerClassName="size-8 justify-center bg-overlay/10 hover:bg-overlay/15 focus-visible:outline-2 focus-visible:outline-primary"
        >
          {thread && notification.space && (
            <DropdownMenuItem>
              <button type="button" className={dropdownMenuItemClasses} onClick={() => props.onMuteThread(notification)}>
                Mute this thread
              </button>
            </DropdownMenuItem>
          )}
          {notification.space && (
            <DropdownMenuItem>
              <button type="button" className={dropdownMenuItemClasses} onClick={() => props.onMuteSpace(notification)}>
                Mute {notification.space.name}
              </button>
            </DropdownMenuItem>
          )}
          <DropdownMenuItem>
            <button
              type="button"
              aria-expanded={whyOpen}
              aria-controls={`notification-why-${notification.cuid}`}
              className={dropdownMenuItemClasses}
              onClick={() => setWhyOpen((open) => !open)}
            >
              Why did I get this?
            </button>
          </DropdownMenuItem>
        </DropdownMenu>
      </div>
    </li>
  );
}
