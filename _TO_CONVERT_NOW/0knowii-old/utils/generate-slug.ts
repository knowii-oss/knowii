import slugify from 'slugify';
import { getLogger } from './logging';

/**
 * Generate a slug for the given string
 * @param str
 */
export function generateSlug(str: string): string {
  const logger = getLogger('utils');

  logger.trace('Generating slug for: %s', str);

  // Replace underscores by spaces to have nice slugs for usernames (which allow underscores)
  const clean = str.replaceAll('_', ' ');
  const retVal = slugify(clean, {
    trim: true,
    lower: true,
    strict: true,
  });

  logger.trace('Generated slug: %s', retVal);

  return retVal;
}
