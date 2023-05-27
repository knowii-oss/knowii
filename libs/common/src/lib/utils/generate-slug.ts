import slugify from 'slugify';

/**
 * Generate a slug for the given string
 * @param str
 */
export function generateSlug(str: string): string {
  console.debug('Generating slug for: ', str);

  // Replace underscores by spaces to have nice slugs for usernames (which allow underscores)
  const clean = str.replaceAll('_', ' ');
  const retVal = slugify(clean, {
    trim: true,
    lower: true,
    strict: true,
  });

  console.debug('Generated slug: ', retVal);

  return retVal;
}
