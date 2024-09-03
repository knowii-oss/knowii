import { replaceDiacritics } from './replace-diacritics';
import { forbiddenUsernameCharactersRegex } from '../constants-more';

/**
 * Clean the given username
 * WARNING: MUST remain aligned with the clean_username db function in supabase-db-seed.sql
 * @param name
 */
export function cleanUsername(name: string) {
  // trim
  let cleanName = name.trim();

  // replace diacritics
  cleanName = replaceDiacritics(cleanName);

  // replace '-' and ' '
  cleanName = cleanName.replaceAll('-', '_');
  cleanName = cleanName.replaceAll(' ', '_');

  // remove characters that aren't ascii, space or '-'
  cleanName = cleanName.replace(forbiddenUsernameCharactersRegex, '');

  // remove trailing whitespace
  cleanName = cleanName.replace('s+$', '');
  cleanName = cleanName.replace('^s+', '');

  // all lowercase
  cleanName = cleanName.toLowerCase();

  return cleanName;
}
