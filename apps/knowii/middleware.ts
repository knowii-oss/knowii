import { createMiddlewareClient } from '@supabase/auth-helpers-nextjs';
import { NextRequest, NextResponse } from 'next/server';
import { ACCOUNT_URL, APP_BASE_URL, CREATE_COMMUNITY_URL, SIGN_IN_URL } from '@knowii/common';

export async function middleware(req: NextRequest) {
  const res = NextResponse.next();
  const supabaseClient = createMiddlewareClient({ req, res });

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
  matcher: [`${APP_BASE_URL}/:path*`, ACCOUNT_URL, CREATE_COMMUNITY_URL],
};
