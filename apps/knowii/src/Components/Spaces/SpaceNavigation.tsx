import { Link } from '@inertiajs/react';
import type { Space, SpaceGroup } from '@knowii/common';
import { safeHttpUrl, SPACE_TYPE_LABELS } from '@knowii/common';
import { FaArrowUpRightFromSquare, FaLock, FaUserLock } from 'react-icons/fa6';
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

const itemClasses = (active: boolean) =>
  cn(
    'flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm transition-colors',
    'hover:bg-overlay/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
    active ? 'bg-overlay/15 font-bold text-primary' : 'text-primary/90',
  );

function VisibilityIcon({ space }: { space: Space }) {
  if (space.visibility === 'members') {
    return <FaLock aria-label="Members only" className="size-3 shrink-0 text-muted" />;
  }
  if (space.visibility === 'groups') {
    return <FaUserLock aria-label="Access groups only" className="size-3 shrink-0 text-muted" />;
  }
  return null;
}

/**
 * The navigation of a community: space groups and their spaces, in order. A list of links: Tab moves between them.
 * A link space (#1294, ADR 0026) opens its page outside Knowii in a new tab, without sending the address of the
 * community (noreferrer).
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
                const externalUrl = space.type === 'link' ? safeHttpUrl(space.url) : null;
                if (externalUrl) {
                  return (
                    <li key={space.cuid}>
                      <a
                        href={externalUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        {...(onNavigate ? { onClick: onNavigate } : {})}
                        className={itemClasses(false)}
                      >
                        <span aria-hidden="true" className="w-5 shrink-0 text-center">
                          {space.emoji || SPACE_TYPE_LABELS.link.emoji}
                        </span>
                        <span className="min-w-0 grow truncate">
                          {space.name} <span className="sr-only">(opens a page outside Knowii in a new tab)</span>
                        </span>
                        <FaArrowUpRightFromSquare aria-hidden="true" className="size-3 shrink-0 text-muted" />
                        <VisibilityIcon space={space} />
                      </a>
                    </li>
                  );
                }
                return (
                  <li key={space.cuid}>
                    <Link
                      href={getSpaceHref(space)}
                      preserveState={true}
                      {...(onNavigate ? { onClick: onNavigate } : {})}
                      {...(active ? { 'aria-current': 'page' as const } : {})}
                      className={itemClasses(active)}
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
                      <VisibilityIcon space={space} />
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
