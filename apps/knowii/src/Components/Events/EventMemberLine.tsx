import { Link } from '@inertiajs/react';
import type { Author } from '@knowii/common';
import { MEMBER_URL } from '@knowii/common';
import { Avatar } from '@/Components/ui';

export interface EventMemberLineProps {
  member: Author;
  communitySlug: string;
  /**
   * The name is a link to the member page: for a reader who is a member of the community. Else it is text
   */
  link: boolean;
}

/**
 * One member on the screens of events (#1514, #1515): the avatar, the name and the username. The host of the event
 * page and each row of the list "Answers" use it. The link carries the cuid of the member in `data-member-link`, so
 * that a list can give it the focus.
 */
export function EventMemberLine({ member, communitySlug, link }: EventMemberLineProps) {
  return (
    <span className="flex items-center gap-3">
      <Avatar name={member.name} imageUrl={member.avatar_url} size="md" />
      <span className="flex min-w-0 flex-col">
        {link ? (
          <Link
            href={route(MEMBER_URL, { communitySlug, username: member.username })}
            data-member-link={member.cuid}
            className="touch-target rounded font-bold wrap-anywhere underline hover:no-underline focus-visible:outline-2 focus-visible:outline-primary"
          >
            {member.name}
          </Link>
        ) : (
          <span className="font-bold wrap-anywhere">{member.name}</span>
        )}
        <span className="text-sm text-muted">
          <span className="sr-only">username</span> <span aria-hidden="true">@</span>
          {member.username}
        </span>
      </span>
    </span>
  );
}
