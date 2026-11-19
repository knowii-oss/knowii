import { describe, expect, it } from 'vitest';
import type { ComboboxOption } from './Combobox';
import { comboboxOptionMatches } from './Combobox';

// The default filter of the Combobox (#1666). The keyboard path, the pinned values, the status and the limit of the
// list are the stories of Combobox.stories.tsx (stories.spec.tsx runs them).

const brussels: ComboboxOption<string> = { value: 'Europe/Brussels', label: 'Brussels, Europe (UTC+1)', keywords: ['Europe/Brussels'] };
const newYork: ComboboxOption<string> = { value: 'America/New_York', label: 'New York, America (UTC-5)', keywords: ['America/New_York'] };
const saoPaulo: ComboboxOption<string> = { value: 'sao-paulo', label: 'São Paulo' };
const described: ComboboxOption<string> = { value: 'jp-tyo', label: 'Tokyo', description: 'Japan Standard Time' };

describe('the default filter of the Combobox (#1666)', () => {
  it.each([
    ['a part of the label', brussels, 'brus'],
    ['the middle of the label', brussels, 'ssel'],
    ['another case', brussels, 'BRUSSELS'],
    ['the label with its punctuation', brussels, 'Brussels, Europe (UTC+1)'],
    ['the label without its punctuation', brussels, 'brussels europe utc+1'],
    ['a keyword', brussels, 'europe/bru'],
    ['a keyword with a space for its slash', brussels, 'europe bru'],
    ['a space for an underscore', newYork, 'new york'],
    ['an underscore for a space', newYork, 'new_york'],
    ['the identifier as it is', newYork, 'America/New_York'],
    ['a slash for a space', newYork, 'america/new york'],
    ['a hyphen like a space, in an offset', newYork, 'utc-5'],
    ['several spaces, and spaces around the text', newYork, '  new   york  '],
    ['a letter without its accent', saoPaulo, 'sao paulo'],
    ['a letter with an accent that the label does not have', brussels, 'brüssels'],
    ['an accent typed as the label has it', saoPaulo, 'São'],
  ])('finds an option by %s', (_case, option, query) => {
    expect(comboboxOptionMatches(option, query)).toBe(true);
  });

  it.each([
    ['a text that is in no label and no keyword', brussels, 'tokyo'],
    ['words in another order than the label or a keyword', newYork, 'york new'],
    ['another offset', brussels, 'utc+2'],
    ['a plus for a minus', newYork, 'utc+5'],
    ['two words written as one', newYork, 'newyork'],
    ['a part of the description', described, 'japan'],
    ['a part of the value', described, 'tyo'],
  ])('does not find an option by %s', (_case, option, query) => {
    expect(comboboxOptionMatches(option, query)).toBe(false);
  });

  it('finds every option with an empty text', () => {
    expect(comboboxOptionMatches(brussels, '')).toBe(true);
    expect(comboboxOptionMatches(brussels, '   ')).toBe(true);
  });
});
