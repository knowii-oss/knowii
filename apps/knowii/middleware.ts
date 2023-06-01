import { createMiddlewareSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { NextRequest, NextResponse } from 'next/server';
import { SIGN_IN_URL } from '@knowii/common';

export async function middleware(req: NextRequest) {
  const res = NextResponse.next();
  const supabaseClient = createMiddlewareSupabaseClient({ req, res });

  const {
    data: { session },
  } = await supabaseClient.auth.getSession();

  if (!session) {
    const redirectUrl = req.nextUrl.clone();
    redirectUrl.pathname = SIGN_IN_URL;
    redirectUrl.searchParams.set(`redirectedFrom`, req.nextUrl.pathname);
    return NextResponse.redirect(redirectUrl);
  }

  return res;
}

export const config = {
  // List of URLs that require authentication
  // When users are not authenticated and try to access one of those paths, they are redirected to the signin page
  // WARNING: The rules below are evaluated at build time and cannot use variables for some reason
  // Make sure to keep those aligned with constants in constants.ts
  matcher: ['/app/:path*', '/account', '/communities/create'],
};
