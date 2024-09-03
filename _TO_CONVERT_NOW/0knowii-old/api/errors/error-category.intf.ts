export const ErrorCategory = {
  business: 'business',
  security: 'security',
  technical: 'technical',
} as const;

export type ErrorCategory = keyof typeof ErrorCategory;
