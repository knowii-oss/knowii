import type { ReactNode } from 'react';
import { useCallback, useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import type { Community, Space, SpaceGroup } from '@knowii/common';
import {
  communityOnlineChannel,
  knowiiApiClient,
  MEMBERS_URL,
  MESSAGES_URL,
  SPACE_URL,
  usePresence,
  useSocket,
  useTypedPage,
} from '@knowii/common';
import { FaBars, FaEnvelope, FaUsers } from 'react-icons/fa6';
import { Button, cn, CountBadge, Drawer, isCurrentUrl } from '@/Components/ui';
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
 * online under it (#1187), and get two links above the spaces: their messages (#1186) and the member directory (#148).
 * A visitor (nobody signed in, #1387) gets a static page: no channel, no presence, and none of the two links.
 */
export function CommunityShell({ community, spaceGroups, activeSpaceCuid, contentWidth = 'wide', children }: CommunityShellProps) {
  const [drawerOpen, setDrawerOpen] = useState(false);
  const user = useTypedPage().props.auth.user;
  const isMember = community.is_member === true && !!user;
  const showsOnlineStatus = user?.show_online_status !== false;
  const pageUrl = usePage().url;
  const messagesHref = route(MESSAGES_URL, { communitySlug: community.slug });
  const onMessagesPage = isCurrentUrl(messagesHref, pageUrl, 'prefix');
  // The member directory (#148). The page of one member is another address ("/member/..."): the link is not marked there
  const membersHref = route(MEMBERS_URL, { communitySlug: community.slug });
  const onMembersPage = isCurrentUrl(membersHref, pageUrl);
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
    // A visitor opens no connection at all (#1387)
    enabled: !!user,
  });
  // The links of a member above the spaces: the messages, then the member directory. A personal community has one
  // member: it gets no directory link
  const memberLinks = isMember ? (
    <div className="flex flex-col gap-1">
      <MemberAreaLink
        href={messagesHref}
        active={onMessagesPage}
        icon={<FaEnvelope aria-hidden="true" className="w-5 shrink-0" />}
        label="Messages"
        onNavigate={() => setDrawerOpen(false)}
      >
        <CountBadge count={community.unread_direct_messages ?? 0} label="unread" />
      </MemberAreaLink>
      {community.visibility !== 'personal' && (
        <MemberAreaLink
          href={membersHref}
          active={onMembersPage}
          icon={<FaUsers aria-hidden="true" className="w-5 shrink-0" />}
          label="Members"
          onNavigate={() => setDrawerOpen(false)}
        />
      )}
    </div>
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
    enabled: !!user,
  });

  // The community was renamed or changed: the breadcrumb and the titles follow (#1128)
  useSocket({
    channel,
    event: 'community.updated',
    callback: () => router.reload({ only: ['community'] }),
    enabled: !!user,
  });

  const getSpaceHref = useCallback(
    (space: Space) => route(SPACE_URL, { communitySlug: community.slug, spaceSlug: space.slug }),
    [community.slug],
  );

  return (
    <div className="flex flex-col gap-6 md:flex-row">
      <aside aria-label={`${community.name} spaces`} className="hidden w-60 shrink-0 md:block">
        <div className="sticky top-4 flex flex-col gap-6">
          {memberLinks}
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
          {memberLinks && <div className="mb-5">{memberLinks}</div>}
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

interface MemberAreaLinkProps {
  href: string;
  /**
   * The page of the link is open: the link gets `aria-current="page"` and the look of the open item
   */
  active: boolean;
  icon: ReactNode;
  label: string;
  onNavigate: () => void;
  /**
   * What follows the label, at the end of the row: the count of the unread messages
   */
  children?: ReactNode;
}

/**
 * A link of a member above the spaces, with one look for all of them: the direct messages (#1186), with what the member
 * did not read, and the member directory (#148). The open one is marked by a bold text and a fill, never by a color alone.
 */
function MemberAreaLink({ href, active, icon, label, onNavigate, children }: MemberAreaLinkProps) {
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
      {icon}
      <span className="grow">{label}</span>
      {children}
    </Link>
  );
}
