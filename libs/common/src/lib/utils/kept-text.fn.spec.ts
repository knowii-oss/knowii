import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { KeptTextPlace } from './kept-text.fn';
import {
  KEPT_TEXT_FORMAT_VERSION,
  KEPT_TEXT_KEY_PREFIX,
  KEPT_TEXT_MAX_AGE_MS,
  keptTextKey,
  readKeptText,
  removeExpiredKeptTexts,
  removeKeptText,
  removeKeptTextsOfAccount,
  writeKeptText,
} from './kept-text.fn';

/**
 * The storage of a browser, in memory
 */
class MemoryStorage implements Storage {
  private readonly items = new Map<string, string>();

  get length(): number {
    return this.items.size;
  }

  clear(): void {
    this.items.clear();
  }

  getItem(key: string): string | null {
    return this.items.get(key) ?? null;
  }

  key(index: number): string | null {
    return [...this.items.keys()][index] ?? null;
  }

  removeItem(key: string): void {
    this.items.delete(key);
  }

  setItem(key: string, value: string): void {
    this.items.set(key, value);
  }
}

const NOW = Date.UTC(2026, 10, 18, 10, 0, 0);
const ada: KeptTextPlace = { account: 'ada1', kind: 'post', place: 's1' };
const text = { fields: { title: 'Hello', body: 'A long text', notify_now: false }, changedAt: NOW, base: null };

describe('the kept text of an unsent post (#1622)', () => {
  let store: MemoryStorage;

  beforeEach(() => {
    store = new MemoryStorage();
    vi.stubGlobal('localStorage', store);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('writes the text with the account, the kind and the place in the key, and reads it back', () => {
    writeKeptText(ada, text);

    expect(store.length).toBe(1);
    expect(store.key(0)).toBe(`${KEPT_TEXT_KEY_PREFIX}ada1:post:s1`);
    expect(JSON.parse(store.getItem(`${KEPT_TEXT_KEY_PREFIX}ada1:post:s1`) ?? '')).toEqual({
      version: KEPT_TEXT_FORMAT_VERSION,
      account: 'ada1',
      changedAt: NOW,
      base: null,
      fields: { title: 'Hello', body: 'A long text', notify_now: false },
    });
    expect(readKeptText(ada, NOW)).toEqual(text);
  });

  it('keeps one text per place: a new write replaces the one before', () => {
    writeKeptText(ada, text);
    writeKeptText(ada, { ...text, fields: { title: 'Hello again', body: '' } });

    expect(store.length).toBe(1);
    expect(readKeptText(ada, NOW)?.fields).toEqual({ title: 'Hello again', body: '' });
  });

  it('keeps the places and the kinds apart', () => {
    writeKeptText(ada, text);

    expect(readKeptText({ ...ada, place: 's2' }, NOW)).toBeNull();
    expect(readKeptText({ ...ada, kind: 'page' }, NOW)).toBeNull();
  });

  it('never reads the text of another account', () => {
    writeKeptText(ada, text);

    expect(readKeptText({ ...ada, account: 'bob1' }, NOW)).toBeNull();
    // An account whose cuid starts with the cuid of another one
    expect(readKeptText({ ...ada, account: 'ada' }, NOW)).toBeNull();
  });

  it('never reads an entry whose value names another account', () => {
    store.setItem(
      `${KEPT_TEXT_KEY_PREFIX}ada1:post:s1`,
      JSON.stringify({ version: KEPT_TEXT_FORMAT_VERSION, account: 'bob1', changedAt: NOW, base: null, fields: { title: 'Bob' } }),
    );

    expect(readKeptText(ada, NOW)).toBeNull();
  });

  it('refuses an identifier with a separator', () => {
    expect(keptTextKey({ ...ada, account: 'ada1:post' })).toBeNull();
    expect(keptTextKey({ ...ada, place: '' })).toBeNull();

    writeKeptText({ ...ada, account: '' }, text);
    expect(store.length).toBe(0);
  });

  it('removes the text of a place', () => {
    writeKeptText(ada, text);
    writeKeptText({ ...ada, place: 's2' }, text);

    removeKeptText(ada);

    expect(readKeptText(ada, NOW)).toBeNull();
    expect(readKeptText({ ...ada, place: 's2' }, NOW)).not.toBeNull();
  });

  it('removes a text whose last change is more than 7 days ago when it is read', () => {
    writeKeptText(ada, text);

    expect(readKeptText(ada, NOW + KEPT_TEXT_MAX_AGE_MS)).not.toBeNull();
    expect(readKeptText(ada, NOW + KEPT_TEXT_MAX_AGE_MS + 1)).toBeNull();
    expect(store.length).toBe(0);
  });

  it('removes an entry with an unknown version, or that it cannot read', () => {
    store.setItem(`${KEPT_TEXT_KEY_PREFIX}ada1:post:s1`, JSON.stringify({ ...text, version: 99, account: 'ada1' }));
    store.setItem(`${KEPT_TEXT_KEY_PREFIX}ada1:post:s2`, '{not json');

    expect(readKeptText(ada, NOW)).toBeNull();
    expect(readKeptText({ ...ada, place: 's2' }, NOW)).toBeNull();
    expect(store.length).toBe(0);
  });

  it('removes every text of an account on a sign-out, and only those', () => {
    writeKeptText(ada, text);
    writeKeptText({ account: 'ada1', kind: 'page', place: 'p1' }, text);
    writeKeptText({ ...ada, account: 'bob1' }, text);
    writeKeptText({ ...ada, account: 'ada12' }, text);
    store.setItem('knowii:theme', 'light');

    removeKeptTextsOfAccount('ada1');

    expect(readKeptText(ada, NOW)).toBeNull();
    expect(readKeptText({ account: 'ada1', kind: 'page', place: 'p1' }, NOW)).toBeNull();
    expect(readKeptText({ ...ada, account: 'bob1' }, NOW)).not.toBeNull();
    expect(readKeptText({ ...ada, account: 'ada12' }, NOW)).not.toBeNull();
    expect(store.getItem('knowii:theme')).toBe('light');
  });

  it('removes the old and the unreadable texts of every account when the application starts', () => {
    writeKeptText(ada, { ...text, changedAt: NOW - KEPT_TEXT_MAX_AGE_MS - 1 });
    writeKeptText({ ...ada, account: 'bob1' }, { ...text, changedAt: NOW - KEPT_TEXT_MAX_AGE_MS - 1 });
    writeKeptText({ ...ada, place: 's2' }, text);
    store.setItem(`${KEPT_TEXT_KEY_PREFIX}bob1:post:s3`, JSON.stringify({ version: 0 }));
    store.setItem('knowii:theme', 'light');

    removeExpiredKeptTexts(NOW);

    expect([...Array(store.length).keys()].map((index) => store.key(index)).sort()).toEqual([
      `${KEPT_TEXT_KEY_PREFIX}ada1:post:s2`,
      'knowii:theme',
    ]);
  });

  it('is silent when the storage throws, and the reads give nothing', () => {
    const failing = new MemoryStorage();
    const fail = () => {
      throw new DOMException('The quota has been exceeded.', 'QuotaExceededError');
    };
    vi.spyOn(failing, 'setItem').mockImplementation(fail);
    vi.spyOn(failing, 'getItem').mockImplementation(fail);
    vi.spyOn(failing, 'removeItem').mockImplementation(fail);
    vi.spyOn(failing, 'key').mockImplementation(fail);
    vi.stubGlobal('localStorage', failing);

    expect(() => writeKeptText(ada, text)).not.toThrow();
    expect(readKeptText(ada, NOW)).toBeNull();
    expect(() => removeKeptText(ada)).not.toThrow();
    expect(() => removeKeptTextsOfAccount('ada1')).not.toThrow();
    expect(() => removeExpiredKeptTexts(NOW)).not.toThrow();
  });

  it('is silent when the browser blocks the storage', () => {
    vi.stubGlobal('localStorage', undefined);
    Object.defineProperty(globalThis, 'localStorage', {
      configurable: true,
      get: () => {
        throw new DOMException('The operation is insecure.', 'SecurityError');
      },
    });

    expect(() => writeKeptText(ada, text)).not.toThrow();
    expect(readKeptText(ada, NOW)).toBeNull();
    expect(() => removeKeptTextsOfAccount('ada1')).not.toThrow();
  });
});
