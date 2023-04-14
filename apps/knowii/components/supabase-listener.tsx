'use client';

import { useRouter, usePathname } from 'next/navigation';
import { useEffect } from 'react';
import { useSupabase } from './supabase-provider';
import { SIGN_IN_URL } from '@knowii/common';

// this component handles refreshing server data when the user logs in or out
// this method avoids the need to pass a session down to child components
// in order to re-render when the user's session changes
// #elegant!
export function SupabaseListener({ serverAccessToken }: { serverAccessToken?: string }) {
  const { supabase } = useSupabase();
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    const {
      data: { subscription },
    } = supabase.auth.onAuthStateChange((event, session) => {
      if (session?.access_token !== serverAccessToken) {
        // server and client are out of sync
        // reload the page to fetch fresh server data
        // https://beta.nextjs.org/docs/data-fetching/mutating
        router.refresh();
      }

      // FIXME test this!
      // FIXME avoid hardcoded strings here (same as in middlewar.ts
      if (event === 'SIGNED_OUT' && pathname && (pathname.startsWith('/app') || pathname.startsWith('/account'))) {
        router.replace(SIGN_IN_URL);
      }
    });

    return () => {
      subscription.unsubscribe();
    };
  }, [serverAccessToken, router, supabase]);

  return null;
}

export default SupabaseListener;
