import { describe, expect, it } from 'vitest';
import { MAX_INVITE_LINK_USES, MAX_LENGTH_INVITE_LINK_NAME } from '../constants';
import {
  INVITE_LINK_JOIN_STATES,
  INVITE_LINK_STATUSES,
  inviteLinkFormSchema,
  inviteLinkOpeningSchema,
  inviteLinkSchema,
  inviteLinkUseSchema,
} from './invite-link.schema';

const link = {
  cuid: 'l1',
  name: 'Course buyers',
  url: 'https://app.example.com/invite/abcdefghijABCDEFGHIJ0123456789abcdefghij',
  status: 'active',
  active: true,
  expires_at: '2026-11-30T10:00:00.000000Z',
  max_uses: 100,
  uses_count: 3,
  access_groups: [{ cuid: 'g1', name: 'Cohort', fed_by_rule: false }],
  member_tags: [
    { cuid: 't1', name: 'Buyer', color: 'teal', visibility: 'members', members_count: null, created_at: '2026-11-22T10:00:00.000000Z' },
  ],
  created_at: '2026-11-23T10:00:00.000000Z',
  updated_at: '2026-11-23T10:00:00.000000Z',
} as const;

const form = { name: 'Course buyers', access_groups: [], member_tags: [], expires_at: null, max_uses: null };

describe('inviteLinkSchema (#1391)', () => {
  it('reads a link of the API, with or without an end and a maximum', () => {
    expect(inviteLinkSchema.parse(link)).toEqual(link);
    expect(inviteLinkSchema.parse({ ...link, expires_at: null, max_uses: null }).max_uses).toBeNull();
  });

  it('knows the four states and the three states of a person of the server, and refuses another value', () => {
    expect(INVITE_LINK_STATUSES).toEqual(['active', 'off', 'ended', 'full']);
    expect(INVITE_LINK_JOIN_STATES).toEqual(['join', 'member', 'blocked']);
    expect(inviteLinkSchema.safeParse({ ...link, status: 'expired' }).success).toBe(false);
  });

  it('reads who joined and what a link opens for a person', () => {
    const use = {
      member: { cuid: 'u1', name: 'Ada Example', username: 'ada-example', avatar_url: null },
      joined_at: '2026-11-23T10:00:00.000000Z',
    };
    expect(inviteLinkUseSchema.parse(use)).toEqual(use);

    const opening = {
      community: {
        cuid: 'c1',
        name: 'Example',
        slug: 'example',
        description: '',
        visibility: 'private',
        join_policy: 'invitation',
        is_member: false,
      },
      state: 'join',
    };
    expect(inviteLinkOpeningSchema.parse(opening)).toEqual(opening);
    expect(inviteLinkOpeningSchema.safeParse({ ...opening, state: 'unusable' }).success).toBe(false);
  });
});

describe('inviteLinkFormSchema (#1391)', () => {
  it('cleans the name as the server does and counts its characters', () => {
    expect(inviteLinkFormSchema.parse({ ...form, name: '  Course    buyers ' }).name).toBe('Course buyers');
    expect(inviteLinkFormSchema.safeParse({ ...form, name: 'é'.repeat(MAX_LENGTH_INVITE_LINK_NAME) }).success).toBe(true);
    expect(inviteLinkFormSchema.safeParse({ ...form, name: 'é'.repeat(MAX_LENGTH_INVITE_LINK_NAME + 1) }).success).toBe(false);
    expect(inviteLinkFormSchema.safeParse({ ...form, name: 'a' }).success).toBe(false);
    expect(inviteLinkFormSchema.safeParse({ ...form, name: 'Two\nlines' }).success).toBe(false);
    expect(inviteLinkFormSchema.safeParse({ ...form, name: 'Hidden​mark' }).success).toBe(false);
  });

  it('takes a maximum from 1 to 10000, or none', () => {
    expect(inviteLinkFormSchema.parse({ ...form, max_uses: 1 }).max_uses).toBe(1);
    expect(inviteLinkFormSchema.parse({ ...form, max_uses: MAX_INVITE_LINK_USES }).max_uses).toBe(MAX_INVITE_LINK_USES);
    expect(inviteLinkFormSchema.safeParse({ ...form, max_uses: 0 }).success).toBe(false);
    expect(inviteLinkFormSchema.safeParse({ ...form, max_uses: MAX_INVITE_LINK_USES + 1 }).success).toBe(false);
    expect(inviteLinkFormSchema.safeParse({ ...form, max_uses: 1.5 }).success).toBe(false);
  });

  it('holds 50 groups and 20 tags at most', () => {
    expect(inviteLinkFormSchema.safeParse({ ...form, member_tags: Array.from({ length: 21 }, (_, i) => `t${i}`) }).success).toBe(false);
    expect(inviteLinkFormSchema.safeParse({ ...form, access_groups: Array.from({ length: 51 }, (_, i) => `g${i}`) }).success).toBe(false);
  });
});
