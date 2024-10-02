export function common(): string {
  return 'common';
}

// Constants
export * from './constants';

export * from './api/knowii-api-client';

export * from './contexts/app-context';

export * from './hooks/use-debounce.hook.fn';
export * from './hooks/use-typed-page.hook.fn';

export * from './types/base-entity.schema';
export * from './types/community.schema';
export * from './types/community-resource-collection.schema';
export * from './types/resource.schema';
export * from './types/community-resource.schema';
export * from './types/http-status.intf';
export * from './types/identifiable.schema';
export * from './types/jetstream/jetstream-inertia.intf';
export * from './types/social-media-link.schema';
export * from './types/user.schema';
export * from './types/username.schema';

export * from './utils/sleep.fn';

export { default as metadata } from './metadata.json';
