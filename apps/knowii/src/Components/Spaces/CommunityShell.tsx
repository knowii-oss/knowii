import type { ReactNode } from 'react';
import { useCallback, useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import type { Community, Space, SpaceGroup } from '@knowii/common';
import { communityOnlineChannel, knowiiApiClient, MESSAGES_URL, SPACE_URL, usePresence, useSocket, useTypedPage } from '@knowii/common';
import { FaBars, FaEnvelope } from 'react-icons/fa6';
import { Button, cn, Drawer, isCurrentUrl } from '@/Components/ui';
import { OnlineMembers } from '@/Components/Presence/OnlineMembers';
import { SpaceNavigation } from './SpaceNavigation';

export interface CommunityShellProps {
  community: Community;
  spaceGroups: SpaceGroup[];
  activeSpaceCuid?: string | undefined;
  /**
   * wide (default): the content uses the full width next to the navigation (grids, settings). readable: about 75
   * characters per line, for posts, comments and profiles (#1264).
   */
  contentWidth?: 'wide' | 'readable';
  children: ReactNode;
}

/**
 * Community pages: the space navigation in a sidebar (desktop) or a drawer (mobile), next to the content.
 * The navigation reloads when the space tree of the community changes (SpaceTreeChanged event). Members see who is
 * online under it (#1187).
 */
export function CommunityShell({ community, spaceGroups, activeSpaceCuid, contentWidth = 'wide', children }: CommunityShellProps) {
  const [drawerOpen, setDrawerOpen] = useState(false);
  const user = useTypedPage().props.auth.user;
  const isMember = community.is_member === true && !!user;
  const showsOnlineStatus = user?.show_online_status !== false;
  const messagesHref = route(MESSAGES_URL, { communitySlug: community.slug });
  const onMessagesPage = isCurrentUrl(messagesHref, usePage().url, 'prefix');
  // A direct message arrived or went: the unread count of the navigation follows (#1186). The messages page reloads it itself
  const userChannel = useMemo(() => ({ type: 'user' as const, userCuid: user?.cuid ?? '' }), [user?.cuid]);
  useSocket({
    channel: userChannel,
    event: ['conversation.message.created', 'conversation.message.deleted'],
    callback: () => {
      if (isMember && !onMessagesPage) {
        router.reload({ only: ['community'] });
      }
    },
  });
  const messagesLink = isMember ? (
    <MessagesLink
      href={messagesHref}
      active={onMessagesPage}
      unread={community.unread_direct_messages ?? 0}
      onNavigate={() => setDrawerOpen(false)}
    />
  ) : null;

  const fetchOnlineMembers = useCallback(() => knowiiApiClient.communities.onlineMembers(community.cuid), [community.cuid]);
  const online = usePresence({
    channel: isMember ? communityOnlineChannel(community.cuid) : null,
    visible: showsOnlineStatus,
    fetchMembers: fetchOnlineMembers,
    currentUserCuid: user?.cuid ?? null,
  });

  const channel = useMemo(() => ({ type: 'community' as const, communityCuid: community.cuid }), [community.cuid]);
  // The navigation changed, or the current space (renamed, moved, other visibility): load both again (#1180, #1128).
  // Inertia ignores a prop that the page does not have.
  const reloadSpaceTree = useCallback(() => {
    router.reload({ only: ['spaceGroups', 'space'] });
  }, []);

  useSocket({
    channel,
    event: 'community.space_tree.changed',
    callback: reloadSpaceTree,
  });

  // The community was renamed or changed: the breadcrumb and the titles follow (#1128)
  useSocket({
    channel,
    event: 'community.updated',
    callback: () => router.reload({ only: ['community'] }),
  });

  const getSpaceHref = useCallback(
    (space: Space) => route(SPACE_URL, { communitySlug: community.slug, spaceSlug: space.slug }),
    [community.slug],
  );

  return (
    <div className="flex flex-col gap-6 md:flex-row">
      <aside aria-label={`${community.name} spaces`} className="hidden w-60 shrink-0 md:block">
        <div className="sticky top-4 flex flex-col gap-6">
          {messagesLink}
          <SpaceNavigation spaceGroups={spaceGroups} activeSpaceCuid={activeSpaceCuid} getSpaceHref={getSpaceHref} />
          {isMember && (
            <OnlineMembers
              communitySlug={community.slug}
              members={online.members}
              hidden={!showsOnlineStatus}
              headingId="online-now-sidebar"
            />
          )}
        </div>
      </aside>

      <div className="md:hidden">
        <Button variant="secondary" size="sm" onClick={() => setDrawerOpen(true)}>
          <FaBars aria-hidden="true" />
          Spaces
        </Button>
        <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title={community.name}>
          {messagesLink && <div className="mb-5">{messagesLink}</div>}
          <SpaceNavigation
            spaceGroups={spaceGroups}
            activeSpaceCuid={activeSpaceCuid}
            getSpaceHref={getSpaceHref}
            onNavigate={() => setDrawerOpen(false)}
          />
          {isMember && (
            <OnlineMembers
              communitySlug={community.slug}
              members={online.members}
              hidden={!showsOnlineStatus}
              headingId="online-now-drawer"
              className="mt-6"
            />
          )}
        </Drawer>
      </div>

      {/* A container: the layouts inside adapt to the width left by the navigation, not to the window (#1264) */}
      <div className="@container min-w-0 grow">
        {contentWidth === 'readable' ? <div className="readable-width">{children}</div> : children}
      </div>
    </div>
  );
}

/**
 * The direct messages of the member (#1186), above the spaces, with what they did not read
 */
function MessagesLink({ href, active, unread, onNavigate }: { href: string; active: boolean; unread: number; onNavigate: () => void }) {
  return (
    <Link
      href={href}
      onClick={onNavigate}
      {...(active ? { 'aria-current': 'page' as const } : {})}
      className={cn(
        'flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm transition-colors',
        'hover:bg-overlay/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
        active ? 'bg-overlay/15 font-bold text-primary' : 'text-primary/90',
      )}
    >
      <FaEnvelope aria-hidden="true" className="w-5 shrink-0" />
      <span className="grow">Messages</span>
      {unread > 0 && (
        <span className="shrink-0 rounded-full bg-secondary px-1.5 text-xs font-bold text-white">
          {unread}
          <span className="sr-only"> unread</span>
        </span>
      )}
    </Link>
  );
}
