/**
 * Possible error types
 */
export const ErrorType = {
  authentication: 'authentication',
  authorization: 'authorization',
  notAvailable: 'notAvailable',
  notFound: 'notFound',
  server: 'server',
  validation: 'validation',
} as const;

export type ErrorType = keyof typeof ErrorType;
