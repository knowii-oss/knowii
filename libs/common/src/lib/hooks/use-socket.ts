import { useEffect, useRef } from 'react';
import type { Community } from '../types/community.schema';
import type { CommunityResource } from '../types/community-resource.schema';
import type { Identifiable } from '../types/identifiable.schema';

const COMMUNITIES_CHANNEL = 'communities';
const COMMUNITY_CHANNEL_PARAM_COMMUNITY_CUID = '{communityCuid}';
const COMMUNITY_CHANNEL = `community.${COMMUNITY_CHANNEL_PARAM_COMMUNITY_CUID}`;
// WARNING: aligned with WS_CHANNEL_COMMUNITY_SPACE (Constants.php)
const SPACE_CHANNEL_PARAM_COMMUNITY_CUID = '{communityCuid}';
const SPACE_CHANNEL_PARAM_SPACE_CUID = '{spaceCuid}';
const SPACE_CHANNEL = `community.${SPACE_CHANNEL_PARAM_COMMUNITY_CUID}.space.${SPACE_CHANNEL_PARAM_SPACE_CUID}`;

// Define the channel types
type Channel =
  { type: 'communities' } | { type: 'community'; communityCuid: string } | { type: 'space'; communityCuid: string; spaceCuid: string };

// Define the event types
// WARNING: those MUST match the names defined using broadcastAs on the back-end event classes
const CommunityEvents = {
  CREATED: 'community.created',
  UPDATED: 'community.updated',
  DELETED: 'community.deleted',
} as const;

const ResourceEvents = {
  CREATED: 'community.resource.created',
  UPDATED: 'community.resource.updated',
  DELETED: 'community.resource.deleted',
} as const;

// The navigation of the community changed: reload it (the payload only names the community)
const SpaceTreeEvents = {
  CHANGED: 'community.space_tree.changed',
} as const;

type SpaceTreeEvent = (typeof SpaceTreeEvents)[keyof typeof SpaceTreeEvents];

// The members or the invitations of the community changed (#1242): load them again (the payload only names the community)
type MemberEvent = 'community.members.changed';

// The access groups of the community changed (#1241): load them again (the payload only names the community)
type AccessGroupEvent = 'community.access_groups.changed';

// Posts, comments and reactions of a space changed (#1180): identifiers only, the page loads the item through the API.
// WARNING: aligned with SpaceContentChanged.php
export const SPACE_CONTENT_EVENTS = [
  'space.post.created',
  'space.post.updated',
  'space.post.deleted',
  'space.comment.created',
  'space.comment.updated',
  'space.comment.deleted',
  'space.reactions.changed',
  // Pages of page spaces (#1179)
  'space.page.created',
  'space.page.updated',
  'space.page.deleted',
  'space.pages.reordered',
  // Messages of chat spaces (#1185)
  'space.message.created',
  'space.message.updated',
  'space.message.deleted',
] as const;
export type SpaceContentEvent = (typeof SPACE_CONTENT_EVENTS)[number];

export interface SpaceContentPayload {
  space_cuid: string;
  post_cuid?: string | null;
  resource_cuid?: string | null;
  comment_cuid?: string | null;
  page_cuid?: string | null;
  message_cuid?: string | null;
  // The first message of the thread of a reply (#1185)
  parent_cuid?: string | null;
  author_cuid?: string | null;
}

type CommunityEvent = (typeof CommunityEvents)[keyof typeof CommunityEvents];
type ResourceEvent = (typeof ResourceEvents)[keyof typeof ResourceEvents];

type CommunitiesChannelEvent = CommunityEvent;
type CommunityChannelEvent = Exclude<CommunityEvent, typeof CommunityEvents.CREATED> | SpaceTreeEvent | MemberEvent | AccessGroupEvent;
// The resources of a resources space go to the channel of the space only (ADR 0015)
type SpaceChannelEvent = ResourceEvent | SpaceContentEvent;

type AllEvents = CommunitiesChannelEvent | CommunityChannelEvent | SpaceChannelEvent;

// Map events to their payload types
type EventPayloadMap = {
  // All community events apart from 'community.deleted' have a payload of type Community
  [K in CommunityEvent]: K extends typeof CommunityEvents.DELETED ? Identifiable : Community;
} & {
  // All resource events apart from 'community.resource.deleted' have a payload of type CommunityResource
  [K in ResourceEvent]: K extends typeof ResourceEvents.DELETED ? Identifiable : CommunityResource;
} & {
  [K in SpaceTreeEvent]: { community_cuid: string };
} & {
  [K in MemberEvent]: { community_cuid: string };
} & {
  [K in AccessGroupEvent]: { community_cuid: string };
} & {
  [K in SpaceContentEvent]: SpaceContentPayload;
};

// Define the hook options with improved type safety
type ChannelEvent<ChannelType extends Channel> = ChannelType['type'] extends 'communities'
  ? CommunitiesChannelEvent
  : ChannelType['type'] extends 'community'
    ? CommunityChannelEvent
    : SpaceChannelEvent;

type UseSocketOptions<ChannelType extends Channel, EventType extends ChannelEvent<ChannelType>> = {
  channel: ChannelType;
  event: EventType | EventType[] | 'all';
  callback: (event: EventType, payload: EventPayloadMap[EventType]) => void;
};

// Helper function to get the channel string
const getChannelString = (channel: Channel): string => {
  switch (channel.type) {
    case 'communities':
      return COMMUNITIES_CHANNEL;
    case 'community':
      return COMMUNITY_CHANNEL.replace(COMMUNITY_CHANNEL_PARAM_COMMUNITY_CUID, channel.communityCuid);
    case 'space':
      return SPACE_CHANNEL.replace(SPACE_CHANNEL_PARAM_COMMUNITY_CUID, channel.communityCuid).replace(
        SPACE_CHANNEL_PARAM_SPACE_CUID,
        channel.spaceCuid,
      );
  }
};

// Get all events for a channel type
const getAllEventsForChannelType = (channelType: Channel['type']): AllEvents[] => {
  if (channelType === 'communities') {
    return Object.values(CommunityEvents);
  }

  if (channelType === 'community') {
    return [...Object.values(CommunityEvents).filter((event) => event !== CommunityEvents.CREATED), ...Object.values(SpaceTreeEvents)];
  }

  return [...Object.values(ResourceEvents), ...SPACE_CONTENT_EVENTS];
};

// Number of hooks using each channel: the channel is left when the last one unmounts
const channelUsers = new Map<string, number>();

const retainChannel = (channel: string): void => {
  channelUsers.set(channel, (channelUsers.get(channel) ?? 0) + 1);
};

const releaseChannel = (channel: string): void => {
  const remaining = (channelUsers.get(channel) ?? 1) - 1;
  if (remaining <= 0) {
    channelUsers.delete(channel);
    window.Echo.leaveChannel(`private-${channel}`);
  } else {
    channelUsers.set(channel, remaining);
  }
};

export const useSocket = <ChannelType extends Channel, EventType extends ChannelEvent<ChannelType>>(
  options: UseSocketOptions<ChannelType, EventType>,
) => {
  // Depend on the channel name, not on the object: callers often pass a new object at each render
  const channelString = getChannelString(options.channel);
  const channelType = options.channel.type;
  const eventKey = Array.isArray(options.event) ? options.event.join(',') : options.event;
  const callbackRef = useRef(options.callback);
  callbackRef.current = options.callback;

  useEffect(() => {
    const echo = window.Echo.private(channelString);
    retainChannel(channelString);

    const events: AllEvents[] =
      eventKey === 'all' ? getAllEventsForChannelType(channelType) : (eventKey.split(',').filter((event) => event !== '') as AllEvents[]);

    const handlers = events.map((event) => {
      const handler = (payload: EventPayloadMap[typeof event]) => {
        callbackRef.current(event as EventType, payload as EventPayloadMap[EventType]);
      };
      echo.listen(`.${event}`, handler);
      return { event, handler };
    });

    return () => {
      // Remove only our listeners: other components can listen on the same channel
      handlers.forEach(({ event, handler }) => echo.stopListening(`.${event}`, handler));
      releaseChannel(channelString);
    };
  }, [channelString, eventKey, channelType]);
};
