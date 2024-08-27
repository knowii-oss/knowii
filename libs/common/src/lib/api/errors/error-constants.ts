import { ReusableError } from './reusable-error.intf';

/**
 * Internal Server Error (500)
 */
export const errorInternalServerError: ReusableError = {
  code: 'internal_server_error',
  key: 'errors.internalServerError',
  description: 'We have encountered an unexpected issue',
  statusCode: 500,
  type: 'server',
  category: 'technical',
};

/**
 * Client not authenticated (401)
 */
export const errorClientNotAuthenticated: ReusableError = {
  code: 'client_not_authenticated',
  key: 'errors.clientNotAuthenticated',
  description: 'The client does not have an active session or is not authenticated',
  statusCode: 401,
  type: 'authentication',
  category: 'security',
};

/**
 * Input validation error (400)
 */
export const errorInputValidation: ReusableError = {
  code: 'invalid_request_error',
  key: 'errors.invalidRequestError',
  description: 'The provided request data is incomplete or invalid',
  statusCode: 400,
  type: 'validation',
  category: 'business',
};

/**
 * Community not found (404)
 */
export const errorCommunityNotFound: ReusableError = {
  code: 'community_not_found_error',
  key: 'communityNotFoundError',
  description: 'The community could not be found',
  statusCode: 404,
  type: 'notFound',
  category: 'business',
};

/**
 * Community name not available (409)
 */
export const errorCommunityNameNotAvailable: ReusableError = {
  code: 'community_name_not_available',
  key: 'communityNameNotAvailable',
  description: 'The chosen community name is not available',
  statusCode: 409,
  type: 'notAvailable',
  category: 'business',
};

/**
 * Community slug not available (409)
 */
export const errorCommunitySlugNotAvailable: ReusableError = {
  code: 'community_slug_not_available',
  key: 'communitySlugNotAvailable',
  description: 'The chosen community slug is not available',
  statusCode: 409,
  type: 'notAvailable',
  category: 'business',
};
