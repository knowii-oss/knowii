import { afterEach, describe, expect, it, vi } from 'vitest';
import { knowiiApiClient } from './knowii-api-client';

// The shape that InvitationImportResource.php sends
const invitationImport = {
  cuid: 'i1',
  state: 'preview',
  default_role: 'member',
  default_access_groups: [{ cuid: 'g1', name: 'Paid' }],
  default_member_tags: [],
  ignored_columns: ['phone'],
  counts: { rows: 2, valid: 1, refused: 1, queued: 0, sent: 0, held: 0, failed: 0, skipped: 0, still_invited: 0, not_sent: 0 },
  started_by: { cuid: 'u1', name: 'Ada Example', username: 'ada', avatar_url: null },
  created_at: '2026-11-18T10:00:00.000000Z',
  confirmed_at: null,
  finished_at: null,
  resent_at: null,
  can_resend_not_joined_from: null,
  kept_until: '2026-11-19T10:00:00.000000Z',
};

const row = {
  cuid: 'r1',
  row_number: 3,
  email: 'not valid',
  role: 'member',
  access_groups: [],
  member_tags: [],
  outcome: 'refused',
  reason: 'invalid_email',
  reason_text: 'This is not a valid email address',
  detail: null,
  still_invited: false,
  sent_at: null,
};

const pagination = { limit: 50, offset: 0, previousOffset: null, nextOffset: null, currentPage: 1, pageCount: 1, totalCount: 1 };

function answer(body: unknown, status = 200) {
  const fetchMock = vi.fn().mockResolvedValue({ ok: status < 400, status, json: () => Promise.resolve(body) });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

describe('the invitations from a file (#1390)', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('sends the file and the defaults as a FormData, without a Content-Type of its own', async () => {
    const fetchMock = answer({ data: invitationImport }, 201);
    const file = new File(['email\ngrace@example.com\n'], 'people.csv', { type: 'text/csv' });

    const result = await knowiiApiClient.communities.invitationImports.upload('c1', {
      file,
      default_role: 'moderator',
      access_groups: ['g1', 'g2'],
      member_tags: ['t1'],
    });

    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('/api/v1/communities/c1/invitation-imports');
    expect(init.method).toBe('POST');
    expect(init.headers).toEqual({ Accept: 'application/json' });
    const body = init.body as FormData;
    expect(body).toBeInstanceOf(FormData);
    expect(body.get('file')).toBeInstanceOf(File);
    expect(body.get('default_role')).toBe('moderator');
    expect(body.getAll('access_groups[]')).toEqual(['g1', 'g2']);
    expect(body.getAll('member_tags[]')).toEqual(['t1']);
    expect(result.ok && result.data.ignored_columns).toEqual(['phone']);
  });

  it('keeps the error of the file', async () => {
    answer({ message: 'Invalid', errors: { file: ['The file needs a column named email'] } }, 400);

    const result = await knowiiApiClient.communities.invitationImports.upload('c1', {
      file: new File(['x'], 'people.csv'),
      default_role: 'member',
      access_groups: [],
      member_tags: [],
    });

    expect(!result.ok && result.errors['file']).toEqual(['The file needs a column named email']);
  });

  it('reads the rows with the filter of the outcomes, and sends the confirm and the send again as JSON', async () => {
    const rows = answer({ data: [row], metadata: { pagination } });
    const result = await knowiiApiClient.communities.invitationImports.rows('c1', 'i1', ['refused', 'held'], 50, 0);
    const url = new URL(rows.mock.calls[0]?.[0] as string, 'https://knowii.example.com');
    expect(url.pathname).toBe('/api/v1/communities/c1/invitation-imports/i1/rows');
    expect(Object.fromEntries(url.searchParams)).toEqual({ limit: '50', offset: '0', outcome: 'refused,held' });
    expect(result.ok && result.data.items[0]?.reason_text).toBe('This is not a valid email address');

    const resend = answer({ data: { ...invitationImport, state: 'sending' } }, 202);
    await knowiiApiClient.communities.invitationImports.resend('c1', 'i1', 'not_joined');
    const [resendUrl, init] = resend.mock.calls[0] as [string, RequestInit];
    expect(resendUrl).toBe('/api/v1/communities/c1/invitation-imports/i1/resend');
    expect(init.body).toBe(JSON.stringify({ rows: 'not_joined' }));
  });
});
