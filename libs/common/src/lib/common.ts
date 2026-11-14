export function common(): string {
  return 'common';
}

// Constants
export * from './constants';
export * from './footer-links';

export * from './api/knowii-api-client';
export * from './api/waitlist/join-waitlist-request.schema';
export * from './api/waitlist/join-waitlist-response.schema';
export * from './api/api-result';

export * from './contexts/app-context';

export * from './hooks/use-debounce.hook.fn';
export * from './hooks/use-presence';
export * from './hooks/use-socket';
export * from './hooks/use-typed-page.hook.fn';

export * from './types/base-entity.schema';
export * from './types/community.schema';
export * from './types/community-resource.schema';
export * from './types/http-status.intf';
export * from './types/identifiable.schema';
export * from './types/jetstream/jetstream-inertia.intf';
export * from './types/resource.schema';
export * from './types/new-community-resource.schema';
export * from './types/resource-capture.schema';
export * from './types/social-media-link.schema';
export * from './types/space.schema';
export * from './types/post.schema';
export * from './types/page.schema';
export * from './types/course-material.schema';
export * from './types/chat-message.schema';
export * from './types/comment.schema';
export * from './types/reaction.schema';
export * from './types/member.schema';
export * from './types/access-group.schema';
export * from './types/upload.schema';
export * from './types/toast.intf';
export * from './types/url.schema';
export * from './types/conversation.schema';
export * from './types/presence.schema';
export * from './types/user.schema';
export * from './types/username.schema';

export * from './utils/sleep.fn';
export * from './utils/safe-url.fn';
export * from './utils/video-url.fn';

export { default as metadata } from './metadata.json';
