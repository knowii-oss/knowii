import { afterEach, describe, expect, it, vi } from 'vitest';
import { knowiiApiClient } from './knowii-api-client';

function answer(status: number) {
  const fetchMock = vi.fn().mockResolvedValue({ ok: status < 400, status, json: () => Promise.resolve({}) });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

describe('the deletion of a community', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('names the community in the body of the request, for the check of the server', async () => {
    const fetchMock = answer(204);

    const deleted = await knowiiApiClient.communities.delete({ community: { cuid: 'c1' }, name: 'Knowledge Builders' });

    expect(deleted).toBe(true);
    expect(new URL(fetchMock.mock.calls[0]?.[0] as string, 'https://knowii.example.com').pathname).toBe('/api/v1/communities/c1');
    const options = fetchMock.mock.calls[0]?.[1] as RequestInit;
    expect(options.method).toBe('delete');
    expect(JSON.parse(options.body as string)).toEqual({ name: 'Knowledge Builders' });
  });

  it('answers false when the server refuses, for example for another name', async () => {
    answer(400);

    expect(await knowiiApiClient.communities.delete({ community: { cuid: 'c1' }, name: 'Another name' })).toBe(false);
  });
});
