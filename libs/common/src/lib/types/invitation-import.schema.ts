import { z } from 'zod';
import { memberTagSchema } from './member-tag.schema';
import { assignableCommunityRoleSchema, invitationAccessGroupSchema } from './member.schema';
import { authorSchema } from './post.schema';

/**
 * Invitations from a CSV file (#1390, ADR 0061). WARNING: aligned with the enums KnowiiInvitationImportState,
 * KnowiiInvitationImportOutcome and KnowiiInvitationImportRefusal (PHP)
 */
export const INVITATION_IMPORT_STATES = ['preview', 'sending', 'done'] as const;
export const invitationImportStateSchema = z.enum(INVITATION_IMPORT_STATES);
export type InvitationImportState = z.infer<typeof invitationImportStateSchema>;

export const INVITATION_IMPORT_OUTCOMES = ['valid', 'refused', 'queued', 'sent', 'held', 'failed', 'skipped'] as const;
export const invitationImportOutcomeSchema = z.enum(INVITATION_IMPORT_OUTCOMES);
export type InvitationImportOutcome = z.infer<typeof invitationImportOutcomeSchema>;

export const INVITATION_IMPORT_REFUSALS = [
  'extra_cells',
  'cell_too_long',
  'invalid_email',
  'duplicate_in_file',
  'unknown_role',
  'role_not_allowed',
  'access_groups_not_allowed',
  'unknown_access_group',
  'ambiguous_access_group',
  'tags_not_allowed',
  'unknown_tag',
  'too_many_tags',
  'already_member',
  'removed_by_manager',
  'pending_invitation',
] as const;
export const invitationImportRefusalSchema = z.enum(INVITATION_IMPORT_REFUSALS);
export type InvitationImportRefusal = z.infer<typeof invitationImportRefusalSchema>;

/**
 * The counts of the rows of an import, from the rows. `still_invited`: rows whose invitation still exists. `not_sent`: the
 * rows that "Send the mails that were not sent" takes (held, failed, or queued for longer than the retries of a mail)
 */
export const invitationImportCountsSchema = z.object({
  rows: z.number().int(),
  valid: z.number().int(),
  refused: z.number().int(),
  queued: z.number().int(),
  sent: z.number().int(),
  held: z.number().int(),
  failed: z.number().int(),
  skipped: z.number().int(),
  still_invited: z.number().int(),
  not_sent: z.number().int(),
});
export type InvitationImportCounts = z.infer<typeof invitationImportCountsSchema>;

/**
 * An import of invitations. WARNING: aligned with InvitationImportResource.php
 */
export const invitationImportSchema = z.object({
  cuid: z.string(),
  state: invitationImportStateSchema,
  default_role: assignableCommunityRoleSchema,
  default_access_groups: z.array(invitationAccessGroupSchema),
  default_member_tags: z.array(memberTagSchema),
  ignored_columns: z.array(z.string()),
  counts: invitationImportCountsSchema,
  // The person who uploaded the file; null for a deleted account
  started_by: authorSchema.nullable(),
  created_at: z.string(),
  confirmed_at: z.string().nullable(),
  finished_at: z.string().nullable(),
  resent_at: z.string().nullable(),
  // From when "Invite again the people who did not join" can run: null before the send and after it ran
  can_resend_not_joined_from: z.string().nullable(),
  // The end of the preview, or the day when the record is deleted
  kept_until: z.string(),
});
export type InvitationImport = z.infer<typeof invitationImportSchema>;

/**
 * One row of an import. WARNING: aligned with InvitationImportRowResource.php
 */
export const invitationImportRowSchema = z.object({
  cuid: z.string(),
  row_number: z.number().int(),
  email: z.string(),
  role: assignableCommunityRoleSchema.nullable(),
  access_groups: z.array(z.string()),
  member_tags: z.array(z.string()),
  outcome: invitationImportOutcomeSchema,
  reason: invitationImportRefusalSchema.nullable(),
  reason_text: z.string().nullable(),
  detail: z.string().nullable(),
  still_invited: z.boolean(),
  sent_at: z.string().nullable(),
});
export type InvitationImportRow = z.infer<typeof invitationImportRowSchema>;

/**
 * The defaults of an upload: the role of a row without one, and the groups and the tags (cuids) of every row
 */
export interface InvitationImportUpload {
  file: File;
  default_role: z.infer<typeof assignableCommunityRoleSchema>;
  access_groups: string[];
  member_tags: string[];
}

/**
 * The two sets of "send again": the held and the failed rows, or the people who did not join
 */
export type InvitationImportResendSet = 'not_sent' | 'not_joined';
