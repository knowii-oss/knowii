import { useState } from 'react';
import { Link } from '@inertiajs/react';
import type { PresenceMember } from '@knowii/common';
import { USER_PROFILE_URL } from '@knowii/common';
import { cn } from '@/Components/ui';
import { OnlineListPanel } from './OnlineListPanel';
import { PresenceAvatars } from './PresenceAvatars';

export interface OnlineMembersProps {
  communitySlug: string;
  members: PresenceMember[];
  // The current member hides their online status: say so, with a way to change it
  hidden: boolean;
  headingId: string;
  className?: string;
}

/**
 * Who is online in the community now (#1187), under the space navigation.
 */
export function OnlineMembers({ communitySlug, members, hidden, headingId, className }: OnlineMembersProps) {
  const [panelOpen, setPanelOpen] = useState(false);

  return (
    <section aria-labelledby={headingId} className={cn('flex flex-col gap-2', className)}>
      <h2 id={headingId} className="px-2 text-xs font-bold tracking-wide text-muted uppercase">
        Online now ({members.length})
      </h2>
      {members.length === 0 ? (
        <p className="px-2 text-sm text-muted">Nobody is online.</p>
      ) : (
        <PresenceAvatars
          communitySlug={communitySlug}
          members={members}
          labelledBy={headingId}
          className="px-2"
          onShowAll={() => setPanelOpen(true)}
        />
      )}
      {hidden && (
        <p className="px-2 text-xs text-muted">
          You appear offline.{' '}
          <Link href={route(USER_PROFILE_URL)} className="underline underline-offset-2 hover:text-primary">
            Change it in your profile
          </Link>
        </p>
      )}
      <OnlineListPanel
        open={panelOpen}
        onClose={() => setPanelOpen(false)}
        title="Online now"
        communitySlug={communitySlug}
        members={members}
      />
    </section>
  );
}
