import { remove } from 'diacritics';

export function replaceDiacritics(str: string) {
  let retVal = remove(str);
  return retVal;
}
