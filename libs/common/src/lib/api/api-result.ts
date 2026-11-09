import type { z } from 'zod';
import { HttpStatus } from '../types/http-status.intf';

/**
 * Result of an API call: the parsed data, or a message and the field errors. Never throws.
 */
export type ApiResult<T> =
  { ok: true; message: string; data: T } | { ok: false; status: number; message: string; errors: Record<string, string[]> };

export type ApiMethod = 'get' | 'post' | 'patch' | 'delete';

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
 * Call the Knowii API (session cookie, JSON) and parse the `data` of the response with the given schema.
 * Pass `null` as schema for responses without content (204).
 */
export async function apiRequest<T>(
  method: ApiMethod,
  url: string,
  schema: z.ZodType<T> | null,
  failureMessage: string,
  body?: unknown,
): Promise<ApiResult<T>> {
  try {
    const response = await fetch(url, {
      // fetch() uppercases GET, POST, DELETE... but not PATCH: servers reject a lowercase "patch" method
      method: method.toUpperCase(),
      headers: jsonHeaders,
      ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });

    if (response.status === HttpStatus.NO_CONTENT) {
      return { ok: true, message: '', data: undefined as T };
    }

    const json: unknown = await response.json();
    const envelope = typeof json === 'object' && json !== null ? (json as Record<string, unknown>) : {};
    const message = typeof envelope['message'] === 'string' && envelope['message'] !== '' ? envelope['message'] : failureMessage;

    if (!response.ok) {
      return { ok: false, status: response.status, message, errors: fieldErrors(envelope['errors']) };
    }

    if (schema === null) {
      return { ok: true, message, data: undefined as T };
    }

    const parsed = schema.safeParse(envelope['data']);
    if (!parsed.success) {
      return { ok: false, status: response.status, message: failureMessage, errors: {} };
    }

    return { ok: true, message, data: parsed.data };
  } catch {
    return { ok: false, status: 0, message: failureMessage, errors: {} };
  }
}
