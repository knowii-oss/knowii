import { z } from 'zod';
import { HttpStatus } from '../types/http-status.intf';
import type { Paginated } from '../types/post.schema';
import { paginationSchema } from '../types/post.schema';

/**
 * Result of an API call: the parsed data, or a message and the field errors. Never throws.
 */
export type ApiResult<T> =
  { ok: true; message: string; data: T } | { ok: false; status: number; message: string; errors: Record<string, string[]> };

export type ApiMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

const jsonHeaders: Record<string, string> = {
  Accept: 'application/json',
  'Content-Type': 'application/json',
};

/**
 * Replace the `{param}` placeholders of an API path.
 */
export function apiPath(path: string, params: Record<string, string>): string {
  return Object.entries(params).reduce((result, [placeholder, value]) => result.replace(placeholder, encodeURIComponent(value)), path);
}

export function fieldErrors(value: unknown): Record<string, string[]> {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    return {};
  }
  const result: Record<string, string[]> = {};
  for (const [field, messages] of Object.entries(value)) {
    if (Array.isArray(messages)) {
      result[field] = messages.filter((message): message is string => typeof message === 'string');
    }
  }
  return result;
}

/**
 * What the server answered, before the `data` is parsed: nothing (204), or the envelope with its message
 */
type ApiAnswer =
  | { ok: true; status: number; message: string; envelope: Record<string, unknown> | null }
  | { ok: false; status: number; message: string; errors: Record<string, string[]> };

/**
 * Send one request to the Knowii API (session cookie, JSON or a FormData) and read the envelope of the answer. Never throws.
 */
async function callApi(method: ApiMethod, url: string, failureMessage: string, body?: unknown, signal?: AbortSignal): Promise<ApiAnswer> {
  try {
    const response = await fetch(url, {
      // fetch() uppercases GET, POST, DELETE... but not PATCH: servers reject a lowercase "patch" method
      method: method.toUpperCase(),
      // A FormData body (a file, #1390) gets no Content-Type: the browser sets the multipart boundary
      headers: body instanceof FormData ? { Accept: 'application/json' } : jsonHeaders,
      ...(body === undefined ? {} : { body: body instanceof FormData ? body : JSON.stringify(body) }),
      ...(signal === undefined ? {} : { signal }),
    });

    if (response.status === HttpStatus.NO_CONTENT) {
      return { ok: true, status: response.status, message: '', envelope: null };
    }

    const json: unknown = await response.json();
    const envelope = typeof json === 'object' && json !== null ? (json as Record<string, unknown>) : {};
    const message = typeof envelope['message'] === 'string' && envelope['message'] !== '' ? envelope['message'] : failureMessage;

    if (!response.ok) {
      return { ok: false, status: response.status, message, errors: fieldErrors(envelope['errors']) };
    }

    return { ok: true, status: response.status, message, envelope };
  } catch {
    return { ok: false, status: 0, message: failureMessage, errors: {} };
  }
}

/**
 * Call the Knowii API (session cookie, JSON) and parse the `data` of the response with the given schema.
 * Pass `null` as schema for responses without content (204).
 */
export async function apiRequest<T>(
  method: ApiMethod,
  url: string,
  schema: z.ZodType<T> | null,
  failureMessage: string,
  body?: unknown,
  signal?: AbortSignal,
): Promise<ApiResult<T>> {
  const answer = await callApi(method, url, failureMessage, body, signal);
  if (!answer.ok) {
    return answer;
  }
  if (answer.envelope === null || schema === null) {
    return { ok: true, message: answer.message, data: undefined as T };
  }

  const parsed = schema.safeParse(answer.envelope['data']);
  if (!parsed.success) {
    return { ok: false, status: answer.status, message: failureMessage, errors: {} };
  }

  return { ok: true, message: answer.message, data: parsed.data };
}

/**
 * `apiRequest()` for one page of a list (ADR 0011): a GET that parses the items of `data` with the given schema and the
 * pagination of `metadata`. A refusal keeps the message of the server, like every other call.
 */
export async function apiPageRequest<T>(
  url: string,
  itemSchema: z.ZodType<T>,
  failureMessage: string,
  signal?: AbortSignal,
): Promise<ApiResult<Paginated<T>>> {
  const result = await apiPageRequestWith(url, itemSchema, z.object({}), failureMessage, signal);
  return result.ok ? { ok: true, message: result.message, data: { items: result.data.items, pagination: result.data.pagination } } : result;
}

/**
 * `apiPageRequest()` for a list whose `metadata` holds more than the pagination (the numbers of the answers of an event,
 * #1515): `metadataSchema` names the other keys, and the result holds them in `metadata`.
 */
export async function apiPageRequestWith<T, M>(
  url: string,
  itemSchema: z.ZodType<T>,
  metadataSchema: z.ZodType<M>,
  failureMessage: string,
  signal?: AbortSignal,
): Promise<ApiResult<Paginated<T> & { metadata: M }>> {
  const answer = await callApi('get', url, failureMessage, undefined, signal);
  if (!answer.ok) {
    return answer;
  }

  const envelope = z
    .object({ data: z.array(itemSchema), metadata: z.object({ pagination: paginationSchema }) })
    .safeParse(answer.envelope ?? {});
  const metadata = metadataSchema.safeParse(answer.envelope?.['metadata']);
  if (!envelope.success || !metadata.success) {
    return { ok: false, status: answer.status, message: failureMessage, errors: {} };
  }

  return {
    ok: true,
    message: answer.message,
    data: { items: envelope.data.data, pagination: envelope.data.metadata.pagination, metadata: metadata.data },
  };
}
