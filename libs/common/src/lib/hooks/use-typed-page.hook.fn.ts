import { usePage } from '@inertiajs/react';
import type { InertiaSharedProps } from '../types/jetstream/jetstream-inertia.intf';

export function useTypedPage<T = Record<string, string>>() {
  return usePage<InertiaSharedProps<T>>();
}
