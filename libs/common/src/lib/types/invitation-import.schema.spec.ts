import { describe, expect, it } from 'vitest';
import {
  INVITATION_IMPORT_OUTCOMES,
  INVITATION_IMPORT_REFUSALS,
  INVITATION_IMPORT_STATES,
  invitationImportRowSchema,
  invitationImportSchema,
} from './invitation-import.schema';

describe('the schemas of the invitations from a file (#1390)', () => {
  it('knows the states, the outcomes and the reasons of the server', () => {
    expect(INVITATION_IMPORT_STATES).toEqual(['preview', 'sending', 'done']);
    expect(INVITATION_IMPORT_OUTCOMES).toEqual(['valid', 'refused', 'queued', 'sent', 'held', 'failed', 'skipped']);
    expect(INVITATION_IMPORT_REFUSALS).toHaveLength(15);
    expect(INVITATION_IMPORT_REFUSALS[0]).toBe('extra_cells');
    expect(INVITATION_IMPORT_REFUSALS[14]).toBe('pending_invitation');
  });

  it('reads an import with a deleted uploader, and refuses another state', () => {
    const value = {
      cuid: 'i1',
      state: 'done',
      default_role: 'admin',
      default_access_groups: [],
      default_member_tags: [],
      ignored_columns: [],
      counts: { rows: 1, valid: 0, refused: 0, queued: 0, sent: 1, held: 0, failed: 0, skipped: 0, still_invited: 1, not_sent: 0 },
      started_by: null,
      created_at: '2026-11-18T10:00:00.000000Z',
      confirmed_at: '2026-11-18T10:00:00.000000Z',
      finished_at: '2026-11-18T10:01:00.000000Z',
      resent_at: null,
      can_resend_not_joined_from: '2026-11-21T10:00:00.000000Z',
      kept_until: '2026-12-25T10:00:00.000000Z',
    };
    expect(invitationImportSchema.parse(value)).toEqual(value);
    expect(invitationImportSchema.safeParse({ ...value, state: 'lost' }).success).toBe(false);
  });

  it('reads a row without a role, and refuses an unknown reason', () => {
    const row = {
      cuid: 'r1',
      row_number: 7,
      email: 'grace@example.com',
      role: null,
      access_groups: [],
      member_tags: [],
      outcome: 'refused',
      reason: 'unknown_role',
      reason_text: 'Use member, moderator or admin',
      detail: null,
      still_invited: false,
      sent_at: null,
    };
    expect(invitationImportRowSchema.parse(row)).toEqual(row);
    expect(invitationImportRowSchema.safeParse({ ...row, reason: 'other' }).success).toBe(false);
  });
});
