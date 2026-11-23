import { describe, expect, it } from 'vitest';
import { MAX_ACCESS_GROUPS_PER_COMMUNITY, MAX_MEMBER_TAGS_PER_MEMBER } from '../constants';
import { communityInvitationFormSchema, communityInvitationSchema } from './member.schema';

const invitation = {
  cuid: 'i1',
  email: 'grace@example.com',
  role: 'member',
  created_at: '2026-11-22T10:00:00.000000Z',
  expires_at: '2026-11-29T10:00:00.000000Z',
  is_expired: false,
  bounced_at: null,
  bounce_type: null,
  access_groups: [{ cuid: 'g1', name: 'Paid' }],
  member_tags: [
    {
      cuid: 't1',
      name: 'Founder',
      color: 'purple',
      visibility: 'managers',
      members_count: null,
      created_at: '2026-11-22T10:00:00.000000Z',
    },
  ],
} as const;

describe('communityInvitationSchema (#1390)', () => {
  it('reads an invitation with the access groups and the member tags that it gives at the join', () => {
    expect(communityInvitationSchema.parse(invitation)).toEqual(invitation);
    expect(communityInvitationSchema.parse({ ...invitation, access_groups: [], member_tags: [] }).member_tags).toEqual([]);
  });

  it('refuses an invitation without the two lists', () => {
    const withoutGroups: Record<string, unknown> = { ...invitation };
    delete withoutGroups['access_groups'];
    const withoutTags: Record<string, unknown> = { ...invitation };
    delete withoutTags['member_tags'];
    expect(communityInvitationSchema.safeParse(withoutGroups).success).toBe(false);
    expect(communityInvitationSchema.safeParse(withoutTags).success).toBe(false);
  });
});

describe('the report of the mail provider on an invitation (#1478)', () => {
  it('reads a hard or a soft bounce with its date, and refuses another type', () => {
    expect(
      communityInvitationSchema.parse({ ...invitation, bounced_at: '2026-11-23T10:00:00.000000Z', bounce_type: 'hard' }).bounce_type,
    ).toBe('hard');
    expect(
      communityInvitationSchema.parse({ ...invitation, bounced_at: '2026-11-23T10:00:00.000000Z', bounce_type: 'soft' }).bounce_type,
    ).toBe('soft');
    expect(communityInvitationSchema.safeParse({ ...invitation, bounce_type: 'other' }).success).toBe(false);
  });
});

describe('communityInvitationFormSchema (#1390)', () => {
  it('takes optional lists of cuids, within the limits of the server', () => {
    expect(communityInvitationFormSchema.safeParse({ email: 'grace@example.com', role: 'member' }).success).toBe(true);
    expect(
      communityInvitationFormSchema.safeParse({ email: 'grace@example.com', role: 'member', access_groups: ['g1'], member_tags: ['t1'] })
        .success,
    ).toBe(true);
    const tooManyTags = Array.from({ length: MAX_MEMBER_TAGS_PER_MEMBER + 1 }, (_, index) => `t${index}`);
    const tooManyGroups = Array.from({ length: MAX_ACCESS_GROUPS_PER_COMMUNITY + 1 }, (_, index) => `g${index}`);
    expect(communityInvitationFormSchema.safeParse({ email: 'grace@example.com', role: 'member', member_tags: tooManyTags }).success).toBe(
      false,
    );
    expect(
      communityInvitationFormSchema.safeParse({ email: 'grace@example.com', role: 'member', access_groups: tooManyGroups }).success,
    ).toBe(false);
  });
});
