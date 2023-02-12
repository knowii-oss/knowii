/**
 * Utils
 */
export const IS_BROWSER = typeof window !== 'undefined';
export const IS_PROD = process.env.NODE_ENV === 'production';
export const IS_TEST = process.env.NODE_ENV === 'test';

/**
 * Meta
 */
export const BUILD_TIME = new Date().toString();
export const BUILD_TIMESTAMP = Date.now();
