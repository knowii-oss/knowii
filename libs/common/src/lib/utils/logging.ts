import debug from 'debug';

// Uses debug-level to provide a logging framework
// Reference: https://github.com/commenthol/debug-level

// WARNING: When adding new loggers, those should be mentioned in the DEBUG environment variable (e.g., package.json
export const communitiesLogger = debug('communities');
export const genericLogger = debug('generic');
export const middlewareLogger = debug('middleware');
export const usersLogger = debug('users');
export const utilsLogger = debug('utils');
