export function common(): string {
  return 'common';
}

// Constants
export * from './constants';

// API Utils
export * from './api/errors/error-type.intf';
export * from './api/errors/error-category.intf';
export * from './api/errors/reusable-error.intf';
export * from './api/errors/api-error.schema';
export * from './api/errors/api-errors.schema';
export * from './api/errors/api-response.schema';
export * from './api/errors/error-constants';

// Domain
export * from './domain/identifiable.intf';

// Generated code
export * from './generated';

// Utils
export * from './utils/logging';
