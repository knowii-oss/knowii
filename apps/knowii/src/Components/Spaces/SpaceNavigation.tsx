import { Link } from '@inertiajs/react';
import type { Space, SpaceGroup } from '@knowii/common';
import { SPACE_TYPE_LABELS } from '@knowii/common';
import { FaLock, FaUserLock } from 'react-icons/fa6';
import { cn } from '@/Components/ui';

export interface SpaceNavigationProps {
  spaceGroups: SpaceGroup[];
  /**
   * The space shown on the page, marked with aria-current
   */
  activeSpaceCuid?: string | undefined;
  getSpaceHref: (space: Space) => string;
  /**
   * Called after a click on a space (closes the mobile drawer)
   */
  onNavigate?: () => void;
  className?: string;
}

/**
 * The navigation of a community: space groups and their spaces, in order. A list of links: Tab moves between them.
 */
export function SpaceNavigation({ spaceGroups, activeSpaceCuid, getSpaceHref, onNavigate, className }: SpaceNavigationProps) {
  const groups = spaceGroups.filter((group) => (group.spaces ?? []).length > 0);

  return (
    <nav aria-label="Spaces" className={cn('flex flex-col gap-5', className)}>
      {groups.length === 0 && <p className="text-sm text-muted">No spaces yet.</p>}
      {groups.map((group) => {
        const headingId = `space-group-${group.cuid}`;
        return (
          <div key={group.cuid}>
            <h2 id={headingId} className="mb-1 px-2 text-xs font-bold tracking-wide text-muted uppercase">
              {group.name}
            </h2>
            <ul aria-labelledby={headingId} className="flex flex-col gap-0.5">
              {(group.spaces ?? []).map((space) => {
                const active = space.cuid === activeSpaceCuid;
                return (
                  <li key={space.cuid}>
                    <Link
                      href={getSpaceHref(space)}
                      preserveState={true}
                      {...(onNavigate ? { onClick: onNavigate } : {})}
                      {...(active ? { 'aria-current': 'page' as const } : {})}
                      className={cn(
                        'flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm transition-colors',
                        'hover:bg-overlay/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
                        active ? 'bg-overlay/15 font-bold text-primary' : 'text-primary/90',
                      )}
                    >
                      <span aria-hidden="true" className="w-5 shrink-0 text-center">
                        {space.emoji || SPACE_TYPE_LABELS[space.type].emoji}
                      </span>
                      <span className="min-w-0 grow truncate">{space.name}</span>
                      {/* Chat spaces (#1185): the messages that the member did not read */}
                      {!active && (space.unread_count ?? 0) > 0 && (
                        <span className="shrink-0 rounded-full bg-secondary px-1.5 text-xs font-bold text-white">
                          {space.unread_count}
                          <span className="sr-only"> unread</span>
                        </span>
                      )}
                      {space.visibility === 'members' && <FaLock aria-label="Members only" className="size-3 shrink-0 text-muted" />}
                      {space.visibility === 'groups' && (
                        <FaUserLock aria-label="Access groups only" className="size-3 shrink-0 text-muted" />
                      )}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </div>
        );
      })}
    </nav>
  );
}
