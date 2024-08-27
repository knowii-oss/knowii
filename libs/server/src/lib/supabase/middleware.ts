import { createServerClient } from '@supabase/ssr';
import { type NextRequest, NextResponse } from 'next/server';

/**
 * Refresh the user session if expired. Needed for server-side (SSR)
 * Reference: https://supabase.com/docs/guides/auth/server-side/nextjs
 * @param request
 */
export const updateSession = async (request: NextRequest) => {
  try {
    // Create an unmodified response
    let response = NextResponse.next({
      request: {
        headers: request.headers,
      },
    });

    const supabase = createServerClient(process.env.NEXT_PUBLIC_SUPABASE_URL || '', process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY || '', {
      cookies: {
        getAll() {
          return request.cookies.getAll();
        },
        setAll(cookiesToSet) {
          cookiesToSet.forEach(({ name, value }) => request.cookies.set(name, value));
          response = NextResponse.next({
            request,
          });
          cookiesToSet.forEach(({ name, value, options }) => response.cookies.set(name, value, options));
        },
      },
    });

    // Refresh the user session
    await supabase.auth.getUser();

    return response;
  } catch (e) {
    // A Supabase client could not be created!
    // Probably because of an issue with environment variables
    return NextResponse.next({
      request: {
        headers: request.headers,
      },
    });
  }
};
