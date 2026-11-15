import { accessGroupMemberSchema, entitlementKeysError, parseEntitlementKeys } from './access-group.schema';

describe('parseEntitlementKeys (#1386)', () => {
  it('takes one key per line, or separated by commas or spaces, without duplicates or empty keys', () => {
    expect(parseEntitlementKeys('a.b\nc-d, e_f  a.b\n\n')).toEqual(['a.b', 'c-d', 'e_f']);
    expect(parseEntitlementKeys('')).toEqual([]);
    expect(parseEntitlementKeys(' \n , ')).toEqual([]);
  });
});

describe('entitlementKeysError (#1386)', () => {
  it('accepts letters, digits, dot, dash and underscore up to 100 characters, and 20 keys', () => {
    expect(entitlementKeysError(['Abc.def-1_2', 'a'.repeat(100)], 20)).toBeNull();
    expect(
      entitlementKeysError(
        Array.from({ length: 20 }, (_, index) => `key.${index}`),
        20,
      ),
    ).toBeNull();
    expect(entitlementKeysError([], 20)).toBeNull();
  });

  it('refuses a character that the server refuses, a key that is too long and too many keys', () => {
    for (const key of ['two words', 'a/b', 'a:b', 'a*', '<b>', 'café']) {
      expect(entitlementKeysError([key], 20)).toBe('A key holds letters, digits, dots, dashes and underscores only');
    }
    expect(entitlementKeysError(['a'.repeat(101)], 20)).toBe('A key is too long (maximum 100 characters)');
    expect(
      entitlementKeysError(
        Array.from({ length: 21 }, (_, index) => `key.${index}`),
        20,
      ),
    ).toBe('Use at most 20 keys');
  });
});

describe('accessGroupMemberSchema (#1386)', () => {
  const member = { cuid: 'u1', name: 'Ada', username: 'ada', avatar_url: null };

  it('reads the source of a membership: by hand, by a rule, or both', () => {
    expect(accessGroupMemberSchema.parse({ ...member, sources: ['manual', 'by_rule'] }).sources).toEqual(['manual', 'by_rule']);
    expect(accessGroupMemberSchema.parse({ ...member, sources: ['by_rule'] }).sources).toEqual(['by_rule']);
  });

  it('refuses a member without a source and an unknown source', () => {
    expect(accessGroupMemberSchema.safeParse(member).success).toBe(false);
    expect(accessGroupMemberSchema.safeParse({ ...member, sources: ['other'] }).success).toBe(false);
  });
});
