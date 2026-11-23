import { z } from 'zod';
import { memberTagSchema } from './member-tag.schema';
import { MAX_ACCESS_GROUPS_PER_COMMUNITY, MAX_MEMBER_TAGS_PER_MEMBER } from '../constants';

/**
 * What a member shows to the other members of a community. WARNING: aligned with MemberProfileResource.php
 */
export const memberProfileSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  username: z.string(),
  avatar_url: z.string().nullable().optional(),
  // One line of plain text under the name (#1688): empty unless the member wrote one and shows it. The default is for a
  // page that was built before the field
  headline: z.string().default(''),
  bio: z.string(),
  location: z.string(),
  // Empty unless the member shows it (#1076)
  phone: z.string().default(''),
  social_links: z.record(z.string(), z.string()),
  member_since: z.coerce.date(),
});
export type MemberProfile = z.infer<typeof memberProfileSchema>;

/**
 * The roles of a community, from the highest to the lowest (ADR 0017). WARNING: aligned with KnowiiCommunityMemberRole.php
 */
export const COMMUNITY_ROLES = ['owner', 'admin', 'moderator', 'member'] as const;
export const communityRoleSchema = z.enum(COMMUNITY_ROLES);
export type CommunityRole = z.infer<typeof communityRoleSchema>;

/**
 * The roles that member management gives (the owner is never given).
 */
export const ASSIGNABLE_COMMUNITY_ROLES = ['admin', 'moderator', 'member'] as const;
export const assignableCommunityRoleSchema = z.enum(ASSIGNABLE_COMMUNITY_ROLES);
export type AssignableCommunityRole = z.infer<typeof assignableCommunityRoleSchema>;

export const COMMUNITY_ROLE_LABELS: Record<CommunityRole, string> = {
  owner: 'Owner',
  admin: 'Admin',
  moderator: 'Moderator',
  member: 'Member',
};

/**
 * The role hierarchy of member management (#1242). WARNING: aligned with CommunityRoles.php
 */
export const COMMUNITY_ROLE_RANKS: Record<CommunityRole, number> = { owner: 4, admin: 3, moderator: 2, member: 1 };

/**
 * Whether a user with the role `actor` (and the right to manage members) acts on a member with the role `target`.
 */
export const canActOnRole = (actor: CommunityRole, target: CommunityRole): boolean =>
  COMMUNITY_ROLE_RANKS[target] < COMMUNITY_ROLE_RANKS[actor];

/**
 * The roles that a user with the role `actor` (and the right to manage members) gives.
 */
export const grantableRoles = (actor: CommunityRole): AssignableCommunityRole[] =>
  ASSIGNABLE_COMMUNITY_ROLES.filter((role) => COMMUNITY_ROLE_RANKS[role] < COMMUNITY_ROLE_RANKS[actor]);

/**
 * A member of a community with their role (#1242). WARNING: aligned with MemberWithRoleResource.php
 */
export const communityMemberSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  username: z.string(),
  avatar_url: z.string().nullable().optional(),
  // One line of plain text under the name (#1688): empty unless the member wrote one and shows it (ADR 0020)
  headline: z.string().default(''),
  role: communityRoleSchema,
  // Inertia props and JSON are not parsed into dates everywhere: keep the ISO string
  member_since: z.string().nullable(),
  // The member tags that the reader may see, by name (#1389, ADR 0060). The default is for a page built before the field
  tags: z.array(memberTagSchema).default([]),
  // Whether the other members of this community find the person in the member list (#1466): false only for a member who
  // hides from it, and only a manager and the member themself get such an entry. The default is for a page built before
  // the field
  in_member_list: z.boolean().default(true),
});
export type CommunityMember = z.infer<typeof communityMemberSchema>;

/**
 * The orders of the member list of a community (#148). `role`: the owner, the admins, the moderators, the members, each
 * by username (the API list without `sort`). `name`: the name from A to Z. `newest`: the date of joining, the newest first.
 * WARNING: aligned with KnowiiMemberSort.php
 */
export const MEMBER_SORTS = ['role', 'name', 'newest'] as const;
export const memberSortSchema = z.enum(MEMBER_SORTS);
export type MemberSort = z.infer<typeof memberSortSchema>;

/**
 * The orders that the member directory offers: it never shows the order of the roles
 */
export type MemberDirectorySort = Exclude<MemberSort, 'role'>;

/**
 * A person whom a manager removed: they cannot join the community again by themselves (#1486). WARNING: aligned with
 * CommunityJoinBlockResource.php
 */
export const communityJoinBlockSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  username: z.string(),
  avatar_url: z.string().nullable().optional(),
  removed_at: z.string(),
});
export type CommunityJoinBlock = z.infer<typeof communityJoinBlockSchema>;

/**
 * An access group that an invitation gives at the join (#1390): its cuid and its name
 */
export const invitationAccessGroupSchema = z.object({
  cuid: z.string(),
  name: z.string(),
});
export type InvitationAccessGroup = z.infer<typeof invitationAccessGroupSchema>;

/**
 * A pending invitation (#1242). WARNING: aligned with CommunityInvitationResource.php. The access groups and the member
 * tags that it gives at the join (#1390), by name: an empty list for a reader who may not see them.
 */
export const communityInvitationSchema = z.object({
  cuid: z.string(),
  email: z.string(),
  role: assignableCommunityRoleSchema,
  created_at: z.string(),
  expires_at: z.string(),
  is_expired: z.boolean(),
  // A report of the mail provider on the invitation email (#1478): `hard` (it could not be reached) or `soft` (it did not
  // arrive yet), and when. Null without a report
  bounced_at: z.string().nullable(),
  bounce_type: z.enum(['hard', 'soft']).nullable(),
  access_groups: z.array(invitationAccessGroupSchema),
  member_tags: z.array(memberTagSchema),
});
export type CommunityInvitation = z.infer<typeof communityInvitationSchema>;

export const communityInvitationFormSchema = z.object({
  email: z.email({ error: 'Enter a valid email address' }).max(255),
  role: assignableCommunityRoleSchema,
  // Cuids of the access groups and the member tags that the person gets at the join (#1390)
  access_groups: z.array(z.string()).max(MAX_ACCESS_GROUPS_PER_COMMUNITY).optional(),
  member_tags: z.array(z.string()).max(MAX_MEMBER_TAGS_PER_MEMBER).optional(),
});
export type CommunityInvitationForm = z.infer<typeof communityInvitationFormSchema>;
