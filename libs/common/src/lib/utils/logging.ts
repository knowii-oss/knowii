import pino from 'pino';

import { IS_PROD } from '../constants';

/**
 * Categories of logs, roughly matching functional areas of the application
 */
export type LoggingCategory = 'none' | '*' | 'ui' | 'api' | 'middleware' | 'utils';

/**
 * Configuration of the log levels for each category in DEV
 */
const devLogLevelData: Record<LoggingCategory, pino.Level> = {
  '*': 'debug',
  ui: 'debug',
  api: 'debug',
  none: 'debug',
  middleware: 'debug',
  utils: 'debug',
};

const devLogLevels = new Map<LoggingCategory | string, pino.Level>(Object.entries(devLogLevelData));

/**
 * Configuration of the log levels for each category in PROD
 */
const prodLogLevelData: Record<LoggingCategory, pino.Level> = {
  '*': 'warn',
  ui: 'warn',
  api: 'warn',
  none: 'warn',
  middleware: 'warn',
  utils: 'warn',
};

const prodLogLevels = new Map<LoggingCategory | string, pino.Level>(Object.entries(prodLogLevelData));

/**
 * Return the log level to use for the given category.
 * The levels vary depending on the environment.
 * @param loggingCategory the category to get the level for
 */
export function getLogLevel(loggingCategory: LoggingCategory): pino.Level {
  if (IS_PROD) {
    return prodLogLevels.get(loggingCategory) || prodLogLevels.get('*') || 'warn';
  }

  return devLogLevels.get(loggingCategory) || devLogLevels.get('*') || 'debug';
}

/**
 * Get a configured logger
 * @param loggingCategory the logging category (e.g., communities)
 * @param subCategory the sub-category (context) (e.g., request URL)
 */
export function getLogger(loggingCategory: LoggingCategory, subCategory?: string) {
  const loggerName = `${loggingCategory} ${subCategory ? '- ' + subCategory : ''}`;

  const retVal = pino({
    // References:
    // https://github.com/pinojs/pino/blob/main/docs/api.md
    // https://betterstack.com/community/guides/logging/how-to-install-setup-and-use-pino-to-log-node-js-applications/
    browser: {},
    base: {
      env: process.env.NODE_ENV,
      revision: process.env.VERCEL_GITHUB_COMMIT_SHA,
      node_version: process.version,
      // Add additional info to include in the logs here
    },
    formatters: {
      level: (label) => {
        return { level: label.toUpperCase() };
      },
    },
    level: process.env.KNOWII_LOG_LEVEL || getLogLevel(loggingCategory),
    name: loggerName,
    redact: {
      paths: ['user.password'], // Add any fields to redact here
      censor: '[REDACTED]',
    },
    timestamp: pino.stdTimeFunctions.isoTime,
  });

  return retVal;
}
