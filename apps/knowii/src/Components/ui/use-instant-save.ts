import { useRef, useState } from 'react';
import type { SaveState } from './SaveStatus';

export interface InstantSave<T> {
  /**
   * The value to show: the one being saved, else the last one saved, else the saved value of the props
   */
  value: T;
  /**
   * A save is running: give it to the control as `busy` (it keeps the focus)
   */
  busy: boolean;
  /**
   * Give it to a `<SaveStatus>` next to the control
   */
  saveState: SaveState;
  /**
   * Shows `next` at once and sends the request. On a failure, the control shows the old value again and the status
   * holds the message. Returns the result, or null when a save was already running (the call does nothing then).
   */
  save: <R extends { ok: boolean; message: string }>(next: T, request: () => Promise<R>) => Promise<R | null>;
}

/**
 * The state of a setting that saves at once (#1569): the value shown, the busy state and the status. `savedValue` is
 * the value of the props: when it changes (the page reloads its props after a save), the control follows it.
 */
export function useInstantSave<T>(savedValue: T): InstantSave<T> {
  const [value, setValue] = useState(savedValue);
  const [propValue, setPropValue] = useState(savedValue);
  const [busy, setBusy] = useState(false);
  const [saveState, setSaveState] = useState<SaveState>({ status: 'idle' });
  // The guard of a second change while the first one is saved: the state of the render can be late
  const busyRef = useRef(false);

  if (!Object.is(propValue, savedValue)) {
    setPropValue(savedValue);
    if (!busy) {
      setValue(savedValue);
    }
  }

  const save = async <R extends { ok: boolean; message: string }>(next: T, request: () => Promise<R>): Promise<R | null> => {
    if (busyRef.current) {
      return null;
    }
    busyRef.current = true;
    const previous = value;
    setValue(next);
    setBusy(true);
    setSaveState({ status: 'saving' });
    const result = await request();
    busyRef.current = false;
    setBusy(false);
    if (!result.ok) {
      setValue(previous);
      setSaveState({ status: 'error', message: result.message });
      return result;
    }
    setSaveState({ status: 'saved' });
    return result;
  };

  return { value, busy, saveState, save };
}
