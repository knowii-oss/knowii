import { useCallback, useMemo } from 'react';
import type { ChatMessage, Community, Space } from '@knowii/common';
import { knowiiApiClient, spacePresenceChannel } from '@knowii/common';
import { ChatRoom } from './ChatRoom';
import { spaceChatBackend } from './chat-backend';

export interface ChatSpaceProps {
  community: Community;
  space: Space;
  // WARNING: aligned with SpaceController.php (chat spaces)
  initialMessages: ChatMessage[];
  hasMore: boolean;
  // The first message after the last one the member read: the "new messages" line goes before it
  firstUnreadCuid: string | null;
  currentUserCuid: string | null;
  canWrite: boolean;
  canReact: boolean;
  canModerate: boolean;
  /**
   * Presence (#1187): the current user is a member. False for visitors of a public space: no presence for them
   */
  presenceEnabled?: boolean;
  // The current member shows their online status (they join the presence channel)
  showsOnlineStatus?: boolean;
}

/**
 * A chat space (#1185): a chat room with threads, live through the space channel. Members see who is in the space and
 * who is typing (#1187).
 */
export function ChatSpace(props: ChatSpaceProps) {
  const { community, space } = props;
  const backend = useMemo(() => spaceChatBackend(community.cuid, space.cuid), [community.cuid, space.cuid]);
  const fetchMembers = useCallback(
    () => knowiiApiClient.communities.spaces.onlineMembers(community.cuid, space.cuid),
    [community.cuid, space.cuid],
  );

  return (
    <ChatRoom
      communityCuid={community.cuid}
      communitySlug={community.slug}
      label={`Chat of ${space.name}`}
      composerLabel={`Message ${space.name}`}
      backend={backend}
      live={{ type: 'space', communityCuid: community.cuid, spaceCuid: space.cuid }}
      initialMessages={props.initialMessages}
      hasMore={props.hasMore}
      firstUnreadCuid={props.firstUnreadCuid}
      currentUserCuid={props.currentUserCuid}
      canWrite={props.canWrite}
      canReact={props.canReact}
      canModerate={props.canModerate}
      presence={{
        channel: props.presenceEnabled ? spacePresenceChannel(community.cuid, space.cuid) : null,
        visible: props.showsOnlineStatus !== false,
        fetchMembers,
        showHereNow: true,
      }}
    />
  );
}
