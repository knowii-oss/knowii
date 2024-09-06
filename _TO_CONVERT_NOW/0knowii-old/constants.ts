/**
 * API
 */
export const API_BASE_PATH = '/api/v1';
export const API_COMMUNITIES_BASE_PATH = '/communities';
export const API_USERS_BASE_PATH = '/users';
export const API_USERNAME_AVAILABILITY_CHECK = `${API_BASE_PATH}${API_USERS_BASE_PATH}/is-username-available`;
export const API_COMMUNITY_NAME_AVAILABILITY_CHECK = `${API_BASE_PATH}${API_COMMUNITIES_BASE_PATH}/is-community-name-available`;
export const API_CREATE_NEW_COMMUNITY = `${API_BASE_PATH}${API_COMMUNITIES_BASE_PATH}/create`;

/**
 * URLs
 */
export const COMMUNITY_BASE_URL = '/community';
export const COMMUNITIES_BASE_URL = '/communities';
export const CREATE_COMMUNITY_URL = `${COMMUNITIES_BASE_URL}/create`; // TODO use in UI
export const BLOG_BASE_URL = '/blog';
export const PRIVACY_POLICY_URL = '/privacy';
export const TERMS_OF_USE_URL = '/terms';
export const CONTACT_URL = '/contact';

/**
 * Regexes
 * WARNING: Some of those must match the regexes used by PL/SQL functions defined in supabase-db-seed.sql
 */
export const forbiddenUsernameCharactersRegex = /[^a-zA-Z0-9_-]+/g;
// Used with react hook form forms
export const allowedUsernameCharactersRegex = /[a-zA-Z0-9_-]+/g;
export const minLengthUsername = 3;
export const maxLengthUsername = 36;

export const forbiddenCommunityNameCharactersRegex = /[^a-zA-Z0-9 -]+/g;
// Used with react hook form forms
export const allowedCommunityNameCharactersRegex = /[a-zA-Z0-9 -]+/g;

export const forbiddenCommunitySlugCharactersRegex = /[^a-zA-Z0-9-]+/g;
// Used with react hook form forms
export const allowedCommunitySlugCharactersRegex = /[a-zA-Z0-9-]+/g;

export const minLengthCommunityName = 3;
export const maxLengthCommunityName = 64;

export const minLengthCommunitySlug = 3;
export const maxLengthCommunitySlug = 64;

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

export const ErrorCategory = {
  business: 'business',
  security: 'security',
  technical: 'technical',
} as const;

export type ErrorCategory = keyof typeof ErrorCategory;

/**
 * Errors
 */

/**
 * An error with basic information
 */
interface ReusableError {
  code: string;
  key: string;
  description: string;
  statusCode: number;
  type: ErrorType;
  category: ErrorCategory;
}

export const errorInternalServerError: ReusableError = {
  code: 'internal_server_error',
  key: 'errors.internalServerError',
  description: 'We have encountered an unexpected issue',
  statusCode: 500,
  type: 'server',
  category: 'technical',
};

export const errorClientNotAuthenticated: ReusableError = {
  code: 'client_not_authenticated',
  key: 'errors.clientNotAuthenticated',
  description: 'The client does not have an active session or is not authenticated',
  statusCode: 401,
  type: 'authentication',
  category: 'security',
};

export const errorInputValidation: ReusableError = {
  code: 'invalid_request_error',
  key: 'errors.invalidRequestError',
  description: 'The provided request data is incomplete or invalid',
  statusCode: 400,
  type: 'validation',
  category: 'business',
};

export const errorCommunityNotFound: ReusableError = {
  code: 'community_not_found_error',
  key: 'communityNotFoundError',
  description: 'The community could not be found',
  statusCode: 404,
  type: 'notFound',
  category: 'business',
};

export const errorCommunityNameNotAvailable: ReusableError = {
  code: 'community_name_not_available',
  key: 'communityNameNotAvailable',
  description: 'The chosen community name is not available',
  statusCode: 409,
  type: 'notAvailable',
  category: 'business',
};

export const errorCommunitySlugNotAvailable: ReusableError = {
  code: 'community_slug_not_available',
  key: 'communitySlugNotAvailable',
  description: 'The chosen community slug is not available',
  statusCode: 409,
  type: 'notAvailable',
  category: 'business',
};
