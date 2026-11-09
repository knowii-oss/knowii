import { z } from 'zod';
import { afterEach, vi } from 'vitest';
import { apiPath, apiRequest } from './api-result';

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

describe('apiPath', () => {
  it('replaces and encodes the parameters', () => {
    expect(apiPath('/a/{x}/b/{y}', { '{x}': '1', '{y}': 'a b' })).toBe('/a/1/b/a%20b');
  });
});
