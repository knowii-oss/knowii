import { replaceDiacritics } from './replace-diacritics';
import { forbiddenCommunityNameCharactersRegex } from '../constants';

/**
 * Clean the given community name
 * @param name
 */
export function cleanCommunityName(name: string) {
  // trim
  let cleanName = name.trim();

  // replace diacritics
  cleanName = replaceDiacritics(cleanName);

  // remove characters that aren't ascii, space or '-'
  cleanName = cleanName.replace(forbiddenCommunityNameCharactersRegex, '');

  // remove trailing whitespace
  cleanName = cleanName.replace('s+$', '');
  cleanName = cleanName.replace('^s+', '');

  return cleanName;
}
