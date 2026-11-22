import { z } from 'zod';
import {
  MAX_ACCESS_GROUPS_PER_COMMUNITY,
  MAX_INVITE_LINK_USES,
  MAX_LENGTH_INVITE_LINK_NAME,
  MAX_MEMBER_TAGS_PER_MEMBER,
  MIN_LENGTH_INVITE_LINK_NAME,
} from '../constants';
import { communityJoinPolicySchema, newCommunitySchema } from './community.schema';
import { cleanMemberTagName, memberTagSchema } from './member-tag.schema';
import { authorSchema } from './post.schema';

/**
 * The state of an invite link (#1391, ADR 0062): only `active` lets a person join. WARNING: aligned with
 * KnowiiInviteLinkStatus.php
 */
export const INVITE_LINK_STATUSES = ['active', 'off', 'ended', 'full'] as const;
export const inviteLinkStatusSchema = z.enum(INVITE_LINK_STATUSES);
export type InviteLinkStatus = z.infer<typeof inviteLinkStatusSchema>;

/**
 * What the person who opens a link can do. The API gives `join`, `member` or `blocked`; the page also shows `unusable`
 * (a link that does not work). WARNING: aligned with KnowiiInviteLinkJoinState.php
 */
export const INVITE_LINK_JOIN_STATES = ['join', 'member', 'blocked'] as const;
export const inviteLinkJoinStateSchema = z.enum(INVITE_LINK_JOIN_STATES);
export type InviteLinkJoinState = z.infer<typeof inviteLinkJoinStateSchema>;
export type InviteLinkPageState = InviteLinkJoinState | 'unusable';

/**
 * A group that a link gives. `fed_by_rule`: a rule on an entitlement feeds it, so the link does not give it
 */
export const inviteLinkAccessGroupSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  fed_by_rule: z.boolean(),
});
export type InviteLinkAccessGroup = z.infer<typeof inviteLinkAccessGroupSchema>;

/**
 * An invite link, for the owner and the admins. `url` is the address to share. WARNING: aligned with
 * InviteLinkResource.php
 */
export const inviteLinkSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  url: z.string(),
  status: inviteLinkStatusSchema,
  active: z.boolean(),
  expires_at: z.string().nullable(),
  max_uses: z.number().int().nullable(),
  uses_count: z.number().int(),
  access_groups: z.array(inviteLinkAccessGroupSchema),
  member_tags: z.array(memberTagSchema),
  created_at: z.string(),
  updated_at: z.string(),
});
export type InviteLink = z.infer<typeof inviteLinkSchema>;

/**
 * One join through a link: the member and the date. WARNING: aligned with InviteLinkUseResource.php
 */
export const inviteLinkUseSchema = z.object({
  member: authorSchema,
  joined_at: z.string(),
});
export type InviteLinkUse = z.infer<typeof inviteLinkUseSchema>;

/**
 * The community as the person who opens a link sees it: its public shape (a member gets more fields, which this schema
 * leaves out). WARNING: aligned with CommunityResource.php
 */
export const inviteLinkCommunitySchema = z.object({
  cuid: z.string(),
  name: z.string(),
  slug: z.string(),
  description: z.string(),
  visibility: newCommunitySchema.shape.visibility,
  join_policy: communityJoinPolicySchema,
  is_member: z.boolean(),
});
export type InviteLinkCommunity = z.infer<typeof inviteLinkCommunitySchema>;

/**
 * The read of a link by the person who has it. WARNING: aligned with InviteLinkJoinApiController.php
 */
export const inviteLinkOpeningSchema = z.object({
  community: inviteLinkCommunitySchema,
  state: inviteLinkJoinStateSchema,
});
export type InviteLinkOpening = z.infer<typeof inviteLinkOpeningSchema>;

/**
 * The props of the page of a link (Pages/InviteLinks/Show.tsx). `community` and `token` are null when the link does not
 * work. WARNING: aligned with InviteLinkController.php
 */
export interface InviteLinkPageProps {
  community: InviteLinkCommunity | null;
  state: InviteLinkPageState;
  token: string | null;
}

/**
 * The body of a create or an update. `expires_at` is an ISO 8601 time (null: no end), `max_uses` a whole number (null:
 * no maximum). WARNING: aligned with InviteLinkInputRules.php
 */
export const inviteLinkFormSchema = z.object({
  name: z
    .string()
    .transform(cleanMemberTagName)
    .pipe(
      z
        .string()
        .min(MIN_LENGTH_INVITE_LINK_NAME, {
          error: `Use between ${MIN_LENGTH_INVITE_LINK_NAME} and ${MAX_LENGTH_INVITE_LINK_NAME} characters`,
        })
        // The server counts characters, not UTF-16 units
        .refine((name) => [...name].length <= MAX_LENGTH_INVITE_LINK_NAME, {
          error: `The name is too long (maximum ${MAX_LENGTH_INVITE_LINK_NAME} characters)`,
        })
        .refine((name) => !/[\p{Cc}\p{Zl}\p{Zp}]/u.test(name), { error: 'Write the name on one line' })
        .refine((name) => !/\p{Cf}/u.test(name), { error: 'Remove the invisible characters from the name' }),
    ),
  access_groups: z.array(z.string()).max(MAX_ACCESS_GROUPS_PER_COMMUNITY),
  member_tags: z.array(z.string()).max(MAX_MEMBER_TAGS_PER_MEMBER),
  expires_at: z.string().nullable(),
  max_uses: z
    .number({ error: 'Enter a whole number' })
    .int({ error: 'Enter a whole number' })
    .min(1, { error: `Enter a number from 1 to ${MAX_INVITE_LINK_USES}` })
    .max(MAX_INVITE_LINK_USES, { error: `Enter a number from 1 to ${MAX_INVITE_LINK_USES}` })
    .nullable(),
});
export type InviteLinkFormInput = z.input<typeof inviteLinkFormSchema>;
export type InviteLinkForm = z.output<typeof inviteLinkFormSchema>;

/**
 * A change of a link: the fields of the request only
 */
export type InviteLinkUpdate = Partial<InviteLinkForm> & { active?: boolean };
