import type { Community, InvitationImport, InvitationImportRow, Paginated } from '@knowii/common';
import type { InvitationImportPageProps } from './Index';

export const community = { cuid: 'c1', name: 'Tests', slug: 'tests', description: '', visibility: 'private' } as Community;

export const pageProps: InvitationImportPageProps = {
  community,
  currentRole: 'owner',
  permissions: {
    canAddCommunityMembers: true,
    canManageAccessGroupMembers: true,
    canAssignMemberTags: true,
    canDeleteInvitationImports: true,
  },
  accessGroups: [{ cuid: 'g1', name: 'Paid' }],
  memberTags: [],
  userCuid: 'u1',
};

export const importOf = (changes: Partial<InvitationImport> = {}): InvitationImport => ({
  cuid: 'i1',
  state: 'preview',
  default_role: 'member',
  default_access_groups: [],
  default_member_tags: [],
  ignored_columns: [],
  counts: { rows: 0, valid: 0, refused: 0, queued: 0, sent: 0, held: 0, failed: 0, skipped: 0, still_invited: 0, not_sent: 0 },
  started_by: { cuid: 'u1', name: 'Ada Example', username: 'ada', avatar_url: null },
  created_at: '2026-11-18T10:00:00.000000Z',
  confirmed_at: null,
  finished_at: null,
  resent_at: null,
  can_resend_not_joined_from: null,
  kept_until: '2026-11-19T10:00:00.000000Z',
  ...changes,
});

export const rowOf = (changes: Partial<InvitationImportRow> = {}): InvitationImportRow => ({
  cuid: 'r1',
  row_number: 2,
  email: 'grace@example.com',
  role: 'member',
  access_groups: [],
  member_tags: [],
  outcome: 'valid',
  reason: null,
  reason_text: null,
  detail: null,
  still_invited: false,
  sent_at: null,
  ...changes,
});

export const pageOf = <T>(items: T[], nextOffset: number | null = null): Paginated<T> => ({
  items,
  pagination: {
    limit: 20,
    offset: 0,
    previousOffset: null,
    nextOffset,
    currentPage: 1,
    pageCount: 1,
    totalCount: items.length,
  } as Paginated<T>['pagination'],
});
