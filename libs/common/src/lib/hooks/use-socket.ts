import { useEffect, useRef } from 'react';
import type { Community } from '../types/community.schema';
import type { CommunityResourceCollection } from '../types/community-resource-collection.schema';
import type { CommunityResource } from '../types/community-resource.schema';
import type { Identifiable } from '../types/identifiable.schema';

const COMMUNITIES_CHANNEL = 'communities';
const COMMUNITY_CHANNEL_PARAM_COMMUNITY_CUID = '{communityCuid}';
const COMMUNITY_CHANNEL = `community.${COMMUNITY_CHANNEL_PARAM_COMMUNITY_CUID}`;
const RESOURCE_COLLECTION_CHANNEL_PARAM_COMMUNITY_CUID = '{communityCuid}';
const RESOURCE_COLLECTION_CHANNEL_PARAM_RESOURCE_COLLECTION_CUID = '{resourceCollectionCuid}';
const RESOURCE_COLLECTION_CHANNEL = `community.${RESOURCE_COLLECTION_CHANNEL_PARAM_COMMUNITY_CUID}.resource_collection.${RESOURCE_COLLECTION_CHANNEL_PARAM_RESOURCE_COLLECTION_CUID}`;

// Define the channel types
type Channel =
  | { type: 'communities' }
  | { type: 'community'; communityCuid: string }
  | { type: 'resourceCollection'; communityCuid: string; resourceCollectionCuid: string };

// Define the event types
// WARNING: those MUST match the names defined using broadcastAs on the back-end event classes
const CommunityEvents = {
  CREATED: 'community.created',
  UPDATED: 'community.updated',
  DELETED: 'community.deleted',
} as const;

const ResourceCollectionEvents = {
  CREATED: 'community.resource_collection.created',
  UPDATED: 'community.resource_collection.updated',
  DELETED: 'community.resource_collection.deleted',
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

type CommunityEvent = (typeof CommunityEvents)[keyof typeof CommunityEvents];
type ResourceCollectionEvent = (typeof ResourceCollectionEvents)[keyof typeof ResourceCollectionEvents];
type ResourceEvent = (typeof ResourceEvents)[keyof typeof ResourceEvents];

type CommunitiesChannelEvent = CommunityEvent | ResourceCollectionEvent | ResourceEvent;
type CommunityChannelEvent =
  Exclude<CommunityEvent, typeof CommunityEvents.CREATED> | ResourceCollectionEvent | ResourceEvent | SpaceTreeEvent;
type ResourceCollectionChannelEvent = Exclude<ResourceCollectionEvent, typeof ResourceCollectionEvents.CREATED> | ResourceEvent;

type AllEvents = CommunitiesChannelEvent | CommunityChannelEvent | ResourceCollectionChannelEvent;

// Map events to their payload types
type EventPayloadMap = {
  // All community events apart from 'community.deleted' have a payload of type Community
  [K in CommunityEvent]: K extends typeof CommunityEvents.DELETED ? Identifiable : Community;
} & {
  // All resource collection events apart from 'community.resource_collection.deleted' have a payload of type CommunityResourceCollection
  [K in ResourceCollectionEvent]: K extends typeof ResourceCollectionEvents.DELETED ? Identifiable : CommunityResourceCollection;
} & {
  // All resource events apart from 'community.resource.deleted' have a payload of type CommunityResource
  [K in ResourceEvent]: K extends typeof ResourceEvents.DELETED ? Identifiable : CommunityResource;
} & {
  [K in SpaceTreeEvent]: { community_cuid: string };
};

// Define the hook options with improved type safety
type UseSocketOptions<
  ChannelType extends Channel,
  EventType extends (ChannelType['type'] extends 'communities' ? CommunitiesChannelEvent : CommunityChannelEvent),
> = {
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
    case 'resourceCollection':
      return RESOURCE_COLLECTION_CHANNEL.replace(RESOURCE_COLLECTION_CHANNEL_PARAM_COMMUNITY_CUID, channel.communityCuid).replace(
        RESOURCE_COLLECTION_CHANNEL_PARAM_RESOURCE_COLLECTION_CUID,
        channel.resourceCollectionCuid,
      );
  }
};

// Get all events for a channel type
const getAllEventsForChannelType = (channelType: Channel['type']): AllEvents[] => {
  const allEvents = [...Object.values(CommunityEvents), ...Object.values(ResourceCollectionEvents), ...Object.values(ResourceEvents)];
  const resourceCollectionEvents = [
    ...Object.values(ResourceCollectionEvents).filter((event) => event !== ResourceCollectionEvents.CREATED),
    ...Object.values(ResourceEvents),
  ];

  if (channelType === 'communities') {
    return allEvents;
  }

  if (channelType === 'community') {
    return [...allEvents.filter((event) => event !== CommunityEvents.CREATED), ...Object.values(SpaceTreeEvents)];
  }

  return resourceCollectionEvents;
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

export const useSocket = <
  ChannelType extends Channel,
  EventType extends (ChannelType['type'] extends 'communities'
    ? CommunitiesChannelEvent
    : ChannelType['type'] extends 'community'
      ? CommunityChannelEvent
      : ResourceCollectionChannelEvent),
>(
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
