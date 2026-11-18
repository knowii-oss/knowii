import { z } from 'zod';

/**
 * The text of an unsent post, page or lesson, kept in the storage of the browser (#1622). This module is the only code
 * that reads and writes these entries. The text never leaves the browser: nothing here sends it, and nothing logs it.
 *
 * A failure of the storage (private mode, full, blocked) is silent: the editor works without it.
 */

/**
 * The start of every key: `knowii:kept-text:<account cuid>:<kind>:<place cuid>`
 */
export const KEPT_TEXT_KEY_PREFIX = 'knowii:kept-text:';

/**
 * A kept text is removed when its last change is older than this
 */
export const KEPT_TEXT_MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000;

/**
 * While a person writes, a kept text is written at most once in this time
 */
export const KEPT_TEXT_WRITE_INTERVAL_MS = 1000;

/**
 * The version of the format of the value. An entry with another version is removed.
 */
export const KEPT_TEXT_FORMAT_VERSION = 1;

/**
 * post: a new post (the place is the space). page: a page or a lesson (the place is the page or the lesson for an edit,
 * the space for a new one).
 */
export type KeptTextKind = 'post' | 'page';

/**
 * Where a text is kept: the account that wrote it, the kind and the place
 */
export interface KeptTextPlace {
  account: string;
  kind: KeptTextKind;
  place: string;
}

/**
 * The text fields and the simple choices of a form. No file, no picture data.
 */
export type KeptTextFields = Record<string, string | boolean>;

export interface KeptText {
  fields: KeptTextFields;
  /**
   * The time of the last change, in milliseconds since the epoch
   */
  changedAt: number;
  /**
   * For an edit: the time of the last update of the item on the server when the text was written, in milliseconds.
   * Null for a new item.
   */
  base: number | null;
}

const storedSchema = z.object({
  version: z.literal(KEPT_TEXT_FORMAT_VERSION),
  account: z.string(),
  changedAt: z.number(),
  base: z.number().nullable(),
  fields: z.record(z.string(), z.union([z.string(), z.boolean()])),
});

// A cuid has letters and digits only: a separator in an identifier could make a key of another account match
const IDENTIFIER = /^[A-Za-z0-9_-]+$/;

function storage(): Storage | null {
  try {
    return typeof globalThis.localStorage === 'undefined' ? null : globalThis.localStorage;
  } catch {
    return null;
  }
}

function accountPrefix(account: string): string | null {
  return IDENTIFIER.test(account) ? `${KEPT_TEXT_KEY_PREFIX}${account}:` : null;
}

/**
 * The key of a place, or null when an identifier is not a cuid
 */
export function keptTextKey({ account, kind, place }: KeptTextPlace): string | null {
  const prefix = accountPrefix(account);
  return prefix && IDENTIFIER.test(place) ? `${prefix}${kind}:${place}` : null;
}

function removeKey(store: Storage, key: string): void {
  try {
    store.removeItem(key);
  } catch {
    // The storage is blocked: nothing to do
  }
}

function isExpired(changedAt: number, now: number): boolean {
  return now - changedAt > KEPT_TEXT_MAX_AGE_MS;
}

/**
 * The stored value of a key, or null. An entry that cannot be read, has an unknown version or is too old is removed.
 */
function readEntry(store: Storage, key: string, now: number): z.infer<typeof storedSchema> | null {
  let raw: string | null;
  try {
    raw = store.getItem(key);
  } catch {
    return null;
  }
  if (raw === null) {
    return null;
  }
  let value: unknown;
  try {
    value = JSON.parse(raw);
  } catch {
    removeKey(store, key);
    return null;
  }
  const parsed = storedSchema.safeParse(value);
  if (!parsed.success || isExpired(parsed.data.changedAt, now)) {
    removeKey(store, key);
    return null;
  }
  return parsed.data;
}

/**
 * The kept text of a place for that account, or null. It never returns the entry of another account.
 */
export function readKeptText(place: KeptTextPlace, now: number = Date.now()): KeptText | null {
  const store = storage();
  const key = keptTextKey(place);
  if (!store || !key) {
    return null;
  }
  const entry = readEntry(store, key, now);
  if (!entry || entry.account !== place.account) {
    return null;
  }
  return { fields: entry.fields, changedAt: entry.changedAt, base: entry.base };
}

/**
 * Keep the text of a place (one kept text per place: it replaces the one before)
 */
export function writeKeptText(place: KeptTextPlace, text: KeptText): void {
  const store = storage();
  const key = keptTextKey(place);
  if (!store || !key) {
    return;
  }
  const value: z.infer<typeof storedSchema> = {
    version: KEPT_TEXT_FORMAT_VERSION,
    account: place.account,
    changedAt: text.changedAt,
    base: text.base,
    fields: text.fields,
  };
  try {
    store.setItem(key, JSON.stringify(value));
  } catch {
    // Private mode or a full storage: the text is not kept, the editor works on
  }
}

/**
 * Remove the kept text of a place
 */
export function removeKeptText(place: KeptTextPlace): void {
  const store = storage();
  const key = keptTextKey(place);
  if (store && key) {
    removeKey(store, key);
  }
}

function keptTextKeys(store: Storage, prefix: string): string[] {
  const keys: string[] = [];
  try {
    for (let index = 0; index < store.length; index++) {
      const key = store.key(index);
      if (key?.startsWith(prefix)) {
        keys.push(key);
      }
    }
  } catch {
    // The storage is blocked: no key
  }
  return keys;
}

/**
 * Remove every kept text of an account, in every place: when the account signs out
 */
export function removeKeptTextsOfAccount(account: string): void {
  const store = storage();
  const prefix = accountPrefix(account);
  if (!store || !prefix) {
    return;
  }
  keptTextKeys(store, prefix).forEach((key) => removeKey(store, key));
}

/**
 * Remove the kept texts of every account that are too old or that cannot be read: when the application starts
 */
export function removeExpiredKeptTexts(now: number = Date.now()): void {
  const store = storage();
  if (!store) {
    return;
  }
  keptTextKeys(store, KEPT_TEXT_KEY_PREFIX).forEach((key) => readEntry(store, key, now));
}
