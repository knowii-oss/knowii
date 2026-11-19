import { Link } from '@inertiajs/react';
import type { Author } from '@knowii/common';
import { MEMBER_URL } from '@knowii/common';
import { useIsVisitor } from '@/Components/Reader/Reader';
import { Avatar } from '@/Components/ui';
import { EVENT_WORDS } from './event-words';

export interface EventHostProps {
  /**
   * Null when the account of the host was deleted
   */
  host: Author | null;
  communitySlug: string;
  /**
   * The reader is a member of the community: the member page is for members
   */
  isMember: boolean;
}

/**
 * The host of an event (#1514, design 6.2): the avatar, the name and the username. The name links to the member page for
 * a member only: a visitor and a person who is not a member read the name as text (ADR 0044 point 6).
 */
export function EventHost({ host, communitySlug, isMember }: EventHostProps) {
  const isVisitor = useIsVisitor();

  return (
    <section aria-labelledby="event-host-title" className="flex flex-col gap-2">
      <h2 id="event-host-title" className="text-sm font-bold text-muted">
        {EVENT_WORDS.host}
      </h2>
      {host ? (
        <p className="flex items-center gap-3">
          <Avatar name={host.name} imageUrl={host.avatar_url} size="md" />
          <span className="flex min-w-0 flex-col">
            {isMember && !isVisitor ? (
              <Link
                href={route(MEMBER_URL, { communitySlug, username: host.username })}
                className="touch-target rounded font-bold underline hover:no-underline focus-visible:outline-2 focus-visible:outline-primary"
              >
                {host.name}
              </Link>
            ) : (
              <span className="font-bold">{host.name}</span>
            )}
            <span className="text-sm text-muted">
              <span className="sr-only">username</span> <span aria-hidden="true">@</span>
              {host.username}
            </span>
          </span>
        </p>
      ) : (
        <p className="font-bold">{EVENT_WORDS.deletedMember}</p>
      )}
    </section>
  );
}
