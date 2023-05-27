import pino from 'pino';

/**
 * Categories of logs, roughly matching functional areas of the application
 */
type LoggingCategory = 'none' | '*' | 'communities' | 'users' | 'utils';

/**
 * Configuration of the log levels for each category
 */
const logLevelData: Record<LoggingCategory, pino.Level> = {
  '*': 'warn',
  none: 'debug',
  users: 'debug',
  communities: 'debug',
  utils: 'debug',
};

const logLevels = new Map<LoggingCategory | string, pino.Level>(Object.entries(logLevelData));

function getLogLevel(loggingCategory: LoggingCategory): pino.Level {
  return logLevels.get(loggingCategory) || logLevels.get('*') || 'info';
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
    transport: {
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
