import type { Ref, RefCallback } from 'react';

/**
 * Combine several refs into one callback ref (React 19: the cleanup function detaches every ref).
 */
export function mergeRefs<T>(...refs: Array<Ref<T> | undefined>): RefCallback<T> {
  return (value) => {
    const cleanups = refs.map((ref) => {
      if (typeof ref === 'function') {
        const cleanup = ref(value);
        return typeof cleanup === 'function' ? cleanup : () => ref(null);
      }
      if (ref) {
        ref.current = value;
        return () => {
          ref.current = null;
        };
      }
      return undefined;
    });

    return () => {
      cleanups.forEach((cleanup) => cleanup?.());
    };
  };
}
