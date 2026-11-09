import type { ReactNode } from 'react';
import { useCallback, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import type { Community, Space, SpaceGroup } from '@knowii/common';
import { SPACE_URL, useSocket } from '@knowii/common';
import { FaBars } from 'react-icons/fa6';
import { Button, Drawer } from '@/Components/ui';
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
 * The navigation reloads when the space tree of the community changes (SpaceTreeChanged event).
 */
export function CommunityShell({ community, spaceGroups, activeSpaceCuid, contentWidth = 'wide', children }: CommunityShellProps) {
  const [drawerOpen, setDrawerOpen] = useState(false);

  const channel = useMemo(() => ({ type: 'community' as const, communityCuid: community.cuid }), [community.cuid]);
  const reloadSpaceTree = useCallback(() => {
    router.reload({ only: ['spaceGroups'] });
  }, []);

  useSocket({
    channel,
    event: 'community.space_tree.changed',
    callback: reloadSpaceTree,
  });

  const getSpaceHref = useCallback(
    (space: Space) => route(SPACE_URL, { communitySlug: community.slug, spaceSlug: space.slug }),
    [community.slug],
  );

  return (
    <div className="flex flex-col gap-6 md:flex-row">
      <aside aria-label={`${community.name} spaces`} className="hidden w-60 shrink-0 md:block">
        <SpaceNavigation spaceGroups={spaceGroups} activeSpaceCuid={activeSpaceCuid} getSpaceHref={getSpaceHref} className="sticky top-4" />
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
        </Drawer>
      </div>

      {/* A container: the layouts inside adapt to the width left by the navigation, not to the window (#1264) */}
      <div className="@container min-w-0 grow">
        {contentWidth === 'readable' ? <div className="readable-width">{children}</div> : children}
      </div>
    </div>
  );
}
