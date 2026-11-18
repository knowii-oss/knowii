import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import type { KeptText, KeptTextFields, KeptTextKind, KeptTextPlace } from '@knowii/common';
import { KEPT_TEXT_WRITE_INTERVAL_MS, readKeptText, removeKeptText, useTypedPage, writeKeptText } from '@knowii/common';

/**
 * none: no kept text. kept: the form shows the kept text. changed: the item changed on the server after the kept text
 * was written, and the person did not choose yet between "Use my text" and "Discard": the text is not in the form.
 */
export type KeptTextState = 'none' | 'kept' | 'changed';

export interface UseKeptTextOptions {
  kind: KeptTextKind;
  /**
   * The cuid of the place (the space for a new item, the item for an edit). Null: nothing is kept.
   */
  place: string | null;
  /**
   * The values of the form when it opens without a kept text: empty, or the item of the server
   */
  start: KeptTextFields;
  /**
   * For an edit: the time of the last update of the item on the server. Inertia props hold an ISO string.
   */
  updatedAt?: Date | string | null | undefined;
}

export interface KeptTextControl {
  state: KeptTextState;
  /**
   * The time of the last change of the kept text, in milliseconds
   */
  changedAt: number | null;
  /**
   * The fields of the kept text that are in the form (after the open or "Use my text"), else null. A choice that the
   * form holds outside react-hook-form starts from it.
   */
  restored: KeptTextFields | null;
  /**
   * Read the kept text when the form opens. It gives the fields to put in the form, or null (no kept text, or the
   * item changed: the state is then "changed").
   */
  load: () => KeptTextFields | null;
  /**
   * The person changed the form: the text is written at most once a second
   */
  change: (values: KeptTextFields) => void;
  /**
   * Write a pending change now (the form closes without a change, the page goes away)
   */
  flush: () => void;
  /**
   * "Use my text": the fields of the kept text, to put in the form
   */
  use: () => KeptTextFields | null;
  /**
   * The text is published or saved, or the person discarded it: the entry goes away
   */
  remove: () => void;
  /**
   * "Discard" in the question "Discard your text?": the text of the form goes away. A kept text that was not put in the
   * form (state "changed") stays.
   */
  discardForm: () => void;
}

function toTime(value: Date | string | null | undefined): number | null {
  if (value === null || value === undefined) {
    return null;
  }
  const time = new Date(value).getTime();
  return Number.isNaN(time) ? null : time;
}

function isEmpty(values: KeptTextFields): boolean {
  return Object.values(values).every((value) => typeof value !== 'string' || value.trim() === '');
}

function isSame(values: KeptTextFields, start: KeptTextFields): boolean {
  const keys = new Set([...Object.keys(values), ...Object.keys(start)]);
  return [...keys].every((key) => values[key] === start[key]);
}

/**
 * The text of an unsent post, page or lesson, kept in the browser (#1622). The account is part of the key: another
 * account on the same browser never reads it. Only the module `kept-text.fn` of the common library touches the
 * storage.
 */
export function useKeptText({ kind, place, start, updatedAt }: UseKeptTextOptions): KeptTextControl {
  const account = useTypedPage().props.auth.user?.cuid ?? null;
  const target = useMemo<KeptTextPlace | null>(() => (account && place ? { account, kind, place } : null), [account, kind, place]);
  const base = toTime(updatedAt);
  const [state, setState] = useState<KeptTextState>('none');
  const [changedAt, setChangedAt] = useState<number | null>(null);
  const [restored, setRestored] = useState<KeptTextFields | null>(null);

  // The latest values are read by the timer and the listeners, so they live in refs
  const kept = useRef<KeptText | null>(null);
  const pending = useRef<{ values: KeptTextFields; at: number } | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const paused = useRef(false);
  const latest = useRef({ target, start, base });
  // Before the effects of the form, which read it when the dialog opens
  useLayoutEffect(() => {
    latest.current = { target, start, base };
  });

  const cancel = useCallback(() => {
    if (timer.current !== null) {
      clearTimeout(timer.current);
      timer.current = null;
    }
    pending.current = null;
  }, []);

  const flush = useCallback(() => {
    const change = pending.current;
    cancel();
    const { target: current, start: values, base: time } = latest.current;
    // Undecided ("The page changed after that"): the kept text stays as it is
    if (!change || !current || paused.current) {
      return;
    }
    if (isEmpty(change.values) || isSame(change.values, values)) {
      removeKeptText(current);
      kept.current = null;
      setState('none');
      return;
    }
    kept.current = { fields: change.values, changedAt: change.at, base: time };
    writeKeptText(current, kept.current);
  }, [cancel]);

  const change = useCallback(
    (values: KeptTextFields) => {
      if (!latest.current.target) {
        return;
      }
      pending.current = { values, at: Date.now() };
      timer.current ??= setTimeout(flush, KEPT_TEXT_WRITE_INTERVAL_MS);
    },
    [flush],
  );

  const load = useCallback((): KeptTextFields | null => {
    cancel();
    const { target: current, base: time } = latest.current;
    const found = current ? readKeptText(current) : null;
    kept.current = found;
    setChangedAt(found?.changedAt ?? null);
    // An edit: the kept text goes in the form only when the item did not change on the server since it was written
    const changedOnServer = found !== null && time !== null && found.base !== time;
    paused.current = changedOnServer;
    setState(found === null ? 'none' : changedOnServer ? 'changed' : 'kept');
    const fields = found && !changedOnServer ? found.fields : null;
    setRestored(fields);
    return fields;
  }, [cancel]);

  const use = useCallback((): KeptTextFields | null => {
    paused.current = false;
    setState(kept.current ? 'kept' : 'none');
    setRestored(kept.current?.fields ?? null);
    return kept.current?.fields ?? null;
  }, []);

  const remove = useCallback(() => {
    cancel();
    paused.current = false;
    kept.current = null;
    setState('none');
    setChangedAt(null);
    setRestored(null);
    if (latest.current.target) {
      removeKeptText(latest.current.target);
    }
  }, [cancel]);

  const discardForm = useCallback(() => {
    if (paused.current) {
      cancel();
      return;
    }
    remove();
  }, [cancel, remove]);

  // A closed tab, a reload or a visit to another page: the last change is written first
  useEffect(() => {
    window.addEventListener('pagehide', flush);
    return () => {
      window.removeEventListener('pagehide', flush);
      flush();
    };
  }, [flush]);

  return { state, changedAt, restored, load, change, flush, use, remove, discardForm };
}
