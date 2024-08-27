'user server';

import { createServerClient } from '@supabase/ssr';
import { cookies } from 'next/headers';

import { Database } from '@knowii/common';

/**
 * Create a Supabase server-side client that bypasses RLS
 */
export const createSupabaseAdminServerSideClient = () => {
  const cookieStore = cookies();

  return createServerClient<Database>(process.env.NEXT_PUBLIC_SUPABASE_URL || '', process.env.SUPABASE_SERVICE_ROLE_KEY || '', {
    cookies: {
      getAll() {
        return cookieStore.getAll();
      },
      setAll(cookiesToSet) {
        try {
          cookiesToSet.forEach(({ name, value, options }) => {
            cookieStore.set(name, value, options);
          });
        } catch (error) {
          // The `set` method was called from a Server Component.
          // This can be ignored if you have middleware refreshing
          // user sessions.
        }
      },
    },
  });
};
