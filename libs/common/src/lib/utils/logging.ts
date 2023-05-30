import pino from 'pino';
import { IS_PROD } from '../constants';

/**
 * Categories of logs, roughly matching functional areas of the application
 */
type LoggingCategory = 'none' | '*' | 'communities' | 'users' | 'utils' | 'stripe';

/**
 * Configuration of the log levels for each category in DEV
 */
const devLogLevelData: Record<LoggingCategory, pino.Level> = {
  '*': 'warn',
  none: 'debug',
  users: 'debug',
  communities: 'debug',
  utils: 'debug',
  stripe: 'debug',
};

const devLogLevels = new Map<LoggingCategory | string, pino.Level>(Object.entries(devLogLevelData));

/**
 * Configuration of the log levels for each category in DEV
 */
const prodLogLevelData: Record<LoggingCategory, pino.Level> = {
  '*': 'warn',
  none: 'warn',
  users: 'warn',
  communities: 'warn',
  utils: 'warn',
  stripe: 'warn',
};

const prodLogLevels = new Map<LoggingCategory | string, pino.Level>(Object.entries(prodLogLevelData));

/**
 * Return the log level to use for the given category.
 * The levels vary depending on the environment.
 * @param loggingCategory the category to get the level for
 */
function getLogLevel(loggingCategory: LoggingCategory): pino.Level {
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
    browser: {},
    // pino-pretty is only enabled in production
    transport: IS_PROD
      ? undefined
      : {
          target: 'pino-pretty',
          // Reference: https://github.com/pinojs/pino-pretty
          options: {
            colorize: true,
            colorizeObjects: true,
            // Reference: https://www.npmjs.com/package/dateformat
            translateTime: 'UTC:yyyy-mm-dd HH:MM:ss.l o',
          },
        },
    base: {
      // Not displaying additional information for now
      //env: process.env.NODE_ENV,
      //revision: process.env.VERCEL_GITHUB_COMMIT_SHA,
    },
    name: loggerName,
    level: getLogLevel(loggingCategory),
  });

  return retVal;
}
