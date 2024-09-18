export function common(): string {
  return 'common';
}

// Constants
export * from './constants';

export * from './api/knowii-api-client';
export * from './hooks/use-debounce.hook.fn';
export * from './hooks/use-typed-page.hook.fn';
export * from './types/jetstream/jetstream-inertia.intf';
export * from './types/community.schema';
export * from './types/username.schema';
export * from './types/http-status.intf';
export * from './utils/sleep.fn';

export { default as metadata } from './metadata.json';
