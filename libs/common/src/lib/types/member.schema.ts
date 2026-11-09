import { z } from 'zod';

/**
 * What a member shows to the other members of a community. WARNING: aligned with MemberProfileResource.php
 */
export const memberProfileSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  username: z.string(),
  avatar_url: z.string().nullable().optional(),
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
  role: communityRoleSchema,
  // Inertia props and JSON are not parsed into dates everywhere: keep the ISO string
  member_since: z.string().nullable(),
});
export type CommunityMember = z.infer<typeof communityMemberSchema>;

/**
 * A pending invitation (#1242). WARNING: aligned with CommunityInvitationResource.php
 */
export const communityInvitationSchema = z.object({
  cuid: z.string(),
  email: z.string(),
  role: assignableCommunityRoleSchema,
  created_at: z.string(),
  expires_at: z.string(),
  is_expired: z.boolean(),
});
export type CommunityInvitation = z.infer<typeof communityInvitationSchema>;

export const communityInvitationFormSchema = z.object({
  email: z.email({ error: 'Enter a valid email address' }).max(255),
  role: assignableCommunityRoleSchema,
});
export type CommunityInvitationForm = z.infer<typeof communityInvitationFormSchema>;
