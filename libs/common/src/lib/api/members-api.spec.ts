import { afterEach, describe, expect, it, vi } from 'vitest';
import { knowiiApiClient } from './knowii-api-client';

// The shape that MemberWithRoleResource.php sends
const member = {
  cuid: 'u1',
  name: 'Ada Lovelace',
  username: 'ada',
  avatar_url: null,
  headline: 'Writes about engines',
  role: 'admin',
  member_since: '2026-11-10T10:00:00.000000Z',
};

const pagination = { limit: 20, offset: 20, previousOffset: 0, nextOffset: null, currentPage: 2, pageCount: 2, totalCount: 21 };

function answer(body: unknown, status = 200) {
  const fetchMock = vi.fn().mockResolvedValue({ ok: status < 400, status, json: () => Promise.resolve(body) });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

const urlOf = (fetchMock: ReturnType<typeof answer>) => new URL(fetchMock.mock.calls[0]?.[0] as string, 'https://knowii.example.com');

describe('the member list of a community (#1242, #148)', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('sends no sort when none is given: the server keeps the order of the roles', async () => {
    const fetchMock = answer({ data: [member], metadata: { pagination } });

    const result = await knowiiApiClient.communities.members.list('c1', 20, 20);

    const url = urlOf(fetchMock);
    expect(url.pathname).toBe('/api/v1/communities/c1/members');
    expect(Object.fromEntries(url.searchParams)).toEqual({ limit: '20', offset: '20' });
    expect(result.ok && result.data.items[0]?.headline).toBe('Writes about engines');
    expect(result.ok && result.data.pagination.nextOffset).toBeNull();
  });

  it('sends the query without a sort, as the member management and the member fields do', async () => {
    const fetchMock = answer({ data: [], metadata: { pagination } });

    await knowiiApiClient.communities.members.list('c1', 8, 0, 'ada l');

    expect(Object.fromEntries(urlOf(fetchMock).searchParams)).toEqual({ limit: '8', offset: '0', query: 'ada l' });
  });

  it.each(['name', 'newest', 'role'] as const)('sends the sort "%s" when it is given, with the query', async (sort) => {
    const fetchMock = answer({ data: [member], metadata: { pagination } });

    await knowiiApiClient.communities.members.list('c1', 20, 20, 'ada & co', sort);

    expect(Object.fromEntries(urlOf(fetchMock).searchParams)).toEqual({ limit: '20', offset: '20', query: 'ada & co', sort });
  });

  it('sends the sort without a query', async () => {
    const fetchMock = answer({ data: [member], metadata: { pagination } });

    await knowiiApiClient.communities.members.list('c1', 20, 0, '', 'newest');

    expect(Object.fromEntries(urlOf(fetchMock).searchParams)).toEqual({ limit: '20', offset: '0', sort: 'newest' });
  });

  it('gives the message of the client when the server refuses or the answer has another shape', async () => {
    answer({ message: 'Too Many Attempts.' }, 429);
    const refused = await knowiiApiClient.communities.members.list('c1', 20, 20, '', 'name');
    expect(refused).toMatchObject({ ok: false, status: 429, message: 'Failed to load the members.' });

    answer({ data: [{ cuid: 'u1' }], metadata: { pagination } });
    const wrong = await knowiiApiClient.communities.members.list('c1', 20, 20, '', 'name');
    expect(wrong).toMatchObject({ ok: false, message: 'Failed to load the members.' });
  });
});
