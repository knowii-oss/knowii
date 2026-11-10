import { useState } from 'react';
import type { PresenceMember } from '@knowii/common';
import { Avatar } from '@/Components/ui';
import { OnlineListPanel } from './OnlineListPanel';

export interface HereNowButtonProps {
  communitySlug: string;
  members: PresenceMember[];
}

// Stacked avatars on the button; the panel lists everybody
const STACKED = 3;

/**
 * Who is in the chat space now (#1299): a compact button, "5 here" with a few stacked avatars, that opens the full list
 * in a side panel. It stays one small control however many members are online.
 */
export function HereNowButton({ communitySlug, members }: HereNowButtonProps) {
  const [open, setOpen] = useState(false);
  const count = members.length;

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="flex items-center gap-2 self-start rounded-full border border-border py-1 pr-3 pl-1 text-xs font-bold text-muted hover:bg-overlay/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
      >
        <span aria-hidden="true" className="flex -space-x-2">
          {members.slice(0, STACKED).map((member) => (
            <Avatar
              key={member.cuid}
              name={member.name}
              imageUrl={member.avatar_url}
              size="sm"
              className="size-6 text-[10px] ring-2 ring-surface"
            />
          ))}
        </span>
        <span aria-hidden="true">{count} here</span>
        <span className="sr-only">{count === 1 ? '1 member here, show the list' : `${count} members here, show the list`}</span>
      </button>
      <OnlineListPanel open={open} onClose={() => setOpen(false)} title="Here now" communitySlug={communitySlug} members={members} />
    </>
  );
}
