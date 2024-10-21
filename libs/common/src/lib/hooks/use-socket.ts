import { useEffect } from 'react';
import { Community } from '../types/community.schema';
import { CommunityResourceCollection } from '../types/community-resource-collection.schema';
import { CommunityResource } from '../types/community-resource.schema';
import { Identifiable } from '../types/identifiable.schema';

const COMMUNITIES_CHANNEL = 'communities';
const COMMUNITY_CHANNEL_PARAM_COMMUNITY_CUID = '{communityCuid}';
const COMMUNITY_CHANNEL = `community.${COMMUNITY_CHANNEL_PARAM_COMMUNITY_CUID}`;

// Define the channel types
type Channel = { type: 'communities' } | { type: 'community'; communityCuid: string };

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

type CommunityEvent = (typeof CommunityEvents)[keyof typeof CommunityEvents];
type ResourceCollectionEvent = (typeof ResourceCollectionEvents)[keyof typeof ResourceCollectionEvents];
type ResourceEvent = (typeof ResourceEvents)[keyof typeof ResourceEvents];

type CommunitiesChannelEvent = CommunityEvent | ResourceCollectionEvent | ResourceEvent;
type CommunityChannelEvent = Exclude<CommunityEvent, typeof CommunityEvents.CREATED> | ResourceCollectionEvent | ResourceEvent;

type AllEvents = CommunitiesChannelEvent | CommunityChannelEvent;

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
};

// Define the hook options with improved type safety
type UseSocketOptions<
  ChannelType extends Channel,
  EventType extends ChannelType['type'] extends 'communities' ? CommunitiesChannelEvent : CommunityChannelEvent,
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
  }
};

// Get all events for a channel type
const getAllEvents = (channelType: Channel['type']): AllEvents[] => {
  const allEvents = [...Object.values(CommunityEvents), ...Object.values(ResourceCollectionEvents), ...Object.values(ResourceEvents)];

  return channelType === 'communities' ? allEvents : allEvents.filter((event) => event !== CommunityEvents.CREATED);
};

export const useSocket = <
  ChannelType extends Channel,
  EventType extends ChannelType['type'] extends 'communities' ? CommunitiesChannelEvent : CommunityChannelEvent,
>(
  options: UseSocketOptions<ChannelType, EventType>,
) => {
  useEffect(() => {
    const channelString = getChannelString(options.channel);
    const echo = window.Echo.private(channelString);

    const listenToEvent = (event: AllEvents) => {
      echo.listen(`.${event}`, (payload: EventPayloadMap[typeof event]) => {
        options.callback(event as EventType, payload as EventPayloadMap[EventType]);
      });
    };

    if (options.event === 'all') {
      getAllEvents(options.channel.type).forEach(listenToEvent);
    } else if (Array.isArray(options.event)) {
      options.event.forEach(listenToEvent);
    } else {
      listenToEvent(options.event);
    }

    return () => {
      window.Echo.leaveChannel(`private-${channelString}`);
    };
  }, [options.channel, options.event, options.callback]);
};
