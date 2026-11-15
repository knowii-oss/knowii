import type { MouseEvent } from 'react';
import { Link } from '@inertiajs/react';
import { format } from 'date-fns';
import type { Notification } from '@knowii/common';
import { notificationSentence } from '@knowii/common';
import { Avatar, Button, cn, RelativeTime } from '@/Components/ui';

export interface NotificationLineProps {
  notification: Notification;
  // The address of the exact item, null when it cannot be opened
  href: string | null;
  // The line was new when the list loaded: bold, with the word "New" (never by color alone)
  isNew: boolean;
  // The name of the community is shown when the member is in more than one
  showCommunity: boolean;
  // "Done" was pressed and the server did not answer yet
  busy: boolean;
  // Another device marked it as done while the focus was on it: it stays until the focus leaves
  doneElsewhere: boolean;
  onOpen: (event: MouseEvent, notification: Notification, href: string) => void;
  onDone: (notification: Notification) => void;
  onLeave: (notification: Notification) => void;
  // Callback refs: the panel finds the line and its link to move the focus and to see what is on screen
  registerLine: (element: HTMLLIElement | null) => void;
  registerLink: (element: HTMLAnchorElement | null) => void;
}

/**
 * A line of the Notifications panel (design 5.1): the avatar of the last author, one sentence, the title, a 2-line
 * excerpt, the space and the time. One link (the sentence, stretched over the line) and real buttons.
 */
export function NotificationLine(props: NotificationLineProps) {
  const { notification, href, isNew, showCommunity, busy, doneElsewhere, registerLine, registerLink } = props;
  const sentence = notificationSentence(notification);
  const where = [notification.space?.name, showCommunity ? notification.community.name : null].filter(Boolean).join(' · ');
  const label = `${sentence}${notification.title ? `: ${notification.title}` : ''}`;
  const avatar = notification.actor;

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
        {notification.title && <p className="truncate text-sm text-primary">{notification.title}</p>}
        {notification.excerpt && <p className="line-clamp-2 text-sm break-words text-muted">{notification.excerpt}</p>}
        <p className="flex flex-wrap gap-x-2 text-xs text-muted">
          {where && <span>{where}</span>}
          <span>
            <RelativeTime date={notification.updated_at} />
            <span className="sr-only">, {format(notification.updated_at, 'PPpp')}</span>
          </span>
        </p>
        {doneElsewhere && <p className="text-xs font-bold text-primary">Done on another device</p>}
      </div>
      {/* Above the stretched link; never `disabled` or `loading`: the focus stays on the button */}
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
        className="relative z-10 self-start"
      >
        Done
      </Button>
    </li>
  );
}
