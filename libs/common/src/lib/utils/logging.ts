import pino from 'pino';
import pretty from 'pino-pretty';

import { IS_PROD } from '../constants';

/**
 * Categories of logs, roughly matching functional areas of the application
 */
export type LoggingCategory = 'none' | '*' | 'api' | 'utils';

/**
 * Configuration of the log levels for each category in DEV
 */
const devLogLevelData: Record<LoggingCategory, pino.Level> = {
  '*': 'debug',
  api: 'debug',
  none: 'debug',
  utils: 'debug',
};

const devLogLevels = new Map<LoggingCategory | string, pino.Level>(Object.entries(devLogLevelData));

/**
 * Configuration of the log levels for each category in PROD
 */
const prodLogLevelData: Record<LoggingCategory, pino.Level> = {
  '*': 'warn',
  api: 'warn',
  none: 'warn',
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

  // Using as a stream: https://github.com/pinojs/pino-pretty#usage-as-a-stream
  // Because we want to avoid that issue: https://stackoverflow.com/questions/71938587/unable-to-determine-transport-target-for-pino-pretty
  const stream = pretty({
    levelFirst: true,
    colorize: true,
    ignore: 'time,hostname,pid',
  });

  const retVal = pino(
    {
      browser: {},
      base: {
        // Not displaying additional information for now
        env: process.env.NODE_ENV,
      },
      name: loggerName,
      level: getLogLevel(loggingCategory),
    },
    stream,
  );

  return retVal;
}
