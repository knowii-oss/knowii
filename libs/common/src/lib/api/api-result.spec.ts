import { z } from 'zod';
import { afterEach, vi } from 'vitest';
import { apiPageRequest, apiPageRequestWith, apiPath, apiRequest, readLiveSocketIdWith } from './api-result';

function respond(status: number, body?: unknown) {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(
    new Response(body === undefined ? null : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } }),
  );
}

describe('apiRequest', () => {
  afterEach(() => vi.restoreAllMocks());

  it('parses the data of a successful response', async () => {
    respond(200, { category: 'success', type: 'success', message: 'Done', data: { name: 'A' } });
    const result = await apiRequest('get', '/api', z.object({ name: z.string() }), 'Failed');
    expect(result).toEqual({ ok: true, message: 'Done', data: { name: 'A' } });
  });

  it('returns the message and the field errors of a failure', async () => {
    respond(400, { category: 'business', type: 'validationIssue', message: 'Invalid', errors: { name: ['Too short'] } });
    const result = await apiRequest('post', '/api', z.object({}), 'Failed', { name: 'a' });
    expect(result).toEqual({ ok: false, status: 400, message: 'Invalid', errors: { name: ['Too short'] } });
  });

  it('sends the HTTP method in uppercase (fetch does not normalize PATCH)', async () => {
    respond(204);
    await apiRequest('patch', '/api', null, 'Failed', {});
    expect(vi.mocked(globalThis.fetch).mock.calls[0]?.[1]?.method).toBe('PATCH');
  });

  it('sends the id of the live connection of the page, so that the live update of its own change does not come back to it', async () => {
    respond(204);
    readLiveSocketIdWith(() => '1234.5678');
    await apiRequest('delete', '/api', null, 'Failed');
    expect(new Headers(vi.mocked(globalThis.fetch).mock.calls[0]?.[1]?.headers).get('X-Socket-ID')).toBe('1234.5678');

    // A page without a live connection sends no id
    readLiveSocketIdWith(() => undefined);
    await apiRequest('delete', '/api', null, 'Failed');
    expect(new Headers(vi.mocked(globalThis.fetch).mock.calls[1]?.[1]?.headers).has('X-Socket-ID')).toBe(false);
  });

  it('accepts an empty 204 response', async () => {
    respond(204);
    expect(await apiRequest('delete', '/api', null, 'Failed')).toMatchObject({ ok: true });
  });

  it('fails when the data does not match the schema, or when the network fails', async () => {
    respond(200, { message: 'Done', data: { name: 42 } });
    expect(await apiRequest('get', '/api', z.object({ name: z.string() }), 'Failed')).toMatchObject({ ok: false, message: 'Failed' });

    vi.spyOn(globalThis, 'fetch').mockRejectedValue(new Error('offline'));
    expect(await apiRequest('get', '/api', null, 'Failed')).toEqual({ ok: false, status: 0, message: 'Failed', errors: {} });
  });
});

describe('apiPageRequest', () => {
  afterEach(() => vi.restoreAllMocks());

  const pagination = { limit: 20, offset: 0, previousOffset: null, nextOffset: 20, currentPage: 1, pageCount: 3, totalCount: 45 };
  const item = z.object({ name: z.string() });

  it('gives the items and the pagination of a page', async () => {
    respond(200, { message: 'Items', data: [{ name: 'A' }], metadata: { pagination, counts: { yes: 1 } } });

    expect(await apiPageRequest('/api', item, 'Failed')).toEqual({
      ok: true,
      message: 'Items',
      data: { items: [{ name: 'A' }], pagination },
    });
  });

  it('gives the other keys of the metadata when a schema names them (#1515)', async () => {
    respond(200, { message: 'Answers', data: [{ name: 'A' }], metadata: { pagination, counts: { yes: 12, replay: 3, no: 4 } } });
    const counts = z.object({ counts: z.object({ yes: z.number(), replay: z.number(), no: z.number() }) });

    expect(await apiPageRequestWith('/api', item, counts, 'Failed')).toEqual({
      ok: true,
      message: 'Answers',
      data: { items: [{ name: 'A' }], pagination, metadata: { counts: { yes: 12, replay: 3, no: 4 } } },
    });
  });

  it('fails without throwing when the named keys are missing, and keeps the message and the status of a refusal', async () => {
    const counts = z.object({ counts: z.object({ yes: z.number() }) });
    respond(200, { message: 'Answers', data: [], metadata: { pagination } });
    expect(await apiPageRequestWith('/api', item, counts, 'Failed')).toMatchObject({ ok: false, status: 200, message: 'Failed' });

    respond(403, { message: 'This action is unauthorized.' });
    expect(await apiPageRequestWith('/api', item, counts, 'Failed')).toMatchObject({
      ok: false,
      status: 403,
      message: 'This action is unauthorized.',
    });
  });
});

describe('apiPath', () => {
  it('replaces and encodes the parameters', () => {
    expect(apiPath('/a/{x}/b/{y}', { '{x}': '1', '{y}': 'a b' })).toBe('/a/1/b/a%20b');
  });
});
