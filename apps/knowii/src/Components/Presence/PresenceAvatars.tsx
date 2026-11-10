import { Link } from '@inertiajs/react';
import type { PresenceMember } from '@knowii/common';
import { MEMBER_URL } from '@knowii/common';
import { Avatar, cn } from '@/Components/ui';

export interface PresenceAvatarsProps {
  communitySlug: string;
  members: PresenceMember[];
  // At most this many avatars; "and N more" for the others
  max?: number;
  className?: string;
  // The id of the text that names the list ("Here now")
  labelledBy?: string;
}

/**
 * The avatars of the members who are online (#1187), each a link to the member page. A green dot marks them; the
 * heading or the label of the list says "online" in text, so the color is never the only cue.
 */
export function PresenceAvatars({ communitySlug, members, max = 12, className, labelledBy }: PresenceAvatarsProps) {
  const shown = members.slice(0, max);
  const more = members.length - shown.length;

  return (
    <ul aria-labelledby={labelledBy} className={cn('flex flex-wrap items-center gap-1', className)}>
      {shown.map((member) => (
        <li key={member.cuid}>
          <Link
            href={route(MEMBER_URL, { communitySlug, username: member.username })}
            title={member.name}
            className="relative inline-flex rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
          >
            <Avatar name={member.name} imageUrl={member.avatar_url} size="sm" />
            <span aria-hidden="true" className="absolute right-0 bottom-0 size-2.5 rounded-full bg-success ring-2 ring-surface" />
            <span className="sr-only">{member.name}</span>
          </Link>
        </li>
      ))}
      {more > 0 && <li className="px-1 text-xs text-muted">and {more} more</li>}
    </ul>
  );
}
