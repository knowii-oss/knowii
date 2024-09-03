import { usePage } from '@inertiajs/react';
import { InertiaSharedProps } from '../types/jetstream/jetstream-inertia.intf';

export function useTypedPage<T = Record<string, string>>() {
  return usePage<InertiaSharedProps<T>>();
}
