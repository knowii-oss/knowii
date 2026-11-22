import { describe, expect, it } from 'vitest';
import { MAX_LENGTH_MEMBER_TAG_NAME } from '../constants';
import { communityMemberSchema } from './member.schema';
import {
  cleanMemberTagName,
  MEMBER_TAG_COLORS,
  MEMBER_TAG_PERSONAL_DATA_NOTICE,
  MEMBER_TAG_VISIBILITIES,
  memberTagFormSchema,
  memberTagSchema,
  sortMemberTags,
} from './member-tag.schema';

const tag = {
  cuid: 't1',
  name: 'Founder',
  color: 'purple',
  visibility: 'members',
  members_count: null,
  created_at: '2026-11-22T10:00:00.000000Z',
} as const;

describe('memberTagSchema (#1389)', () => {
  it('reads a tag of the API, with or without its member count', () => {
    expect(memberTagSchema.parse(tag)).toEqual(tag);
    expect(memberTagSchema.parse({ ...tag, members_count: 3 }).members_count).toBe(3);
  });

  it('knows the eight colors and the two visibilities of the server, and refuses another value', () => {
    expect(MEMBER_TAG_COLORS).toEqual(['gray', 'red', 'orange', 'yellow', 'green', 'teal', 'blue', 'purple']);
    expect(MEMBER_TAG_VISIBILITIES).toEqual(['members', 'managers']);
    expect(memberTagSchema.safeParse({ ...tag, color: 'magenta' }).success).toBe(false);
    expect(memberTagSchema.safeParse({ ...tag, visibility: 'public' }).success).toBe(false);
  });

  it('gives a member entry its tags, and none for an entry built before the field', () => {
    const entry = { cuid: 'u1', name: 'Ada', username: 'ada', headline: '', role: 'member', member_since: null };
    expect(communityMemberSchema.parse(entry).tags).toEqual([]);
    expect(communityMemberSchema.parse({ ...entry, tags: [tag] }).tags).toEqual([tag]);
  });
});

describe('the form of a tag (#1389)', () => {
  const form = (name: string) => memberTagFormSchema.safeParse({ name, color: 'gray', visibility: 'managers' });

  it('cleans the name as the server does', () => {
    expect(cleanMemberTagName('  Early    bird  ')).toBe('Early bird');
    expect(form('  Early    bird ').data?.name).toBe('Early bird');
  });

  it('holds the limits of the name, counted in characters', () => {
    expect(form('x').success).toBe(false);
    expect(form('   ').success).toBe(false);
    expect(form('é'.repeat(MAX_LENGTH_MEMBER_TAG_NAME)).success).toBe(true);
    expect(form('é'.repeat(MAX_LENGTH_MEMBER_TAG_NAME + 1)).success).toBe(false);
    // Two UTF-16 units per emoji: 16 of them are 16 characters
    expect(form('🌱'.repeat(16)).success).toBe(true);
    expect(form('Two\nlines').error?.issues[0]?.message).toBe('Write the name on one line');
  });

  it('refuses a format character, so two names never look the same', () => {
    const invisible = 'Remove the invisible characters from the name';
    // A zero width space, a right-to-left override, a left-to-right mark, a soft hyphen
    expect(form('Qzshown\u200B Probe').error?.issues[0]?.message).toBe(invisible);
    expect(form('\u202EredrO').error?.issues[0]?.message).toBe(invisible);
    expect(form('Foun\u200Eder').success).toBe(false);
    expect(form('Fou\u00ADnder').success).toBe(false);
    expect(form('Qzshown Probe').success).toBe(true);
  });

  it('keeps the approved text on personal data word for word', () => {
    expect(MEMBER_TAG_PERSONAL_DATA_NOTICE).toBe(
      'A tag is personal data about each member who has it. Do not record health, religion, political opinions or other sensitive facts in a tag. Each member can read all their tags in the export of their data.',
    );
  });

  it('sorts tags by name without the case', () => {
    const named = (cuid: string, name: string) => ({ ...tag, cuid, name });
    expect(sortMemberTags([named('a', 'founder b'), named('b', 'Alpha'), named('c', 'Founder a')]).map((item) => item.name)).toEqual([
      'Alpha',
      'Founder a',
      'founder b',
    ]);
  });
});
