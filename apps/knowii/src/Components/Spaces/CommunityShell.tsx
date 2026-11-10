import type { ReactNode } from 'react';
import { useCallback, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import type { Community, Space, SpaceGroup } from '@knowii/common';
import { communityOnlineChannel, knowiiApiClient, SPACE_URL, usePresence, useSocket, useTypedPage } from '@knowii/common';
import { FaBars } from 'react-icons/fa6';
import { Button, Drawer } from '@/Components/ui';
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
