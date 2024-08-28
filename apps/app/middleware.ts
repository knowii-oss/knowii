import { type NextRequest, NextResponse } from 'next/server';
import { updateSession } from '@knowii/server';
import { middlewareLogger } from '@knowii/common';

export default async function middleware(request: NextRequest) {
  middlewareLogger('Processing request %o', request);

  await updateSession(request);

  const response = NextResponse.next();
  middlewareLogger('Request', request);
  middlewareLogger('Response', response);

  return response;
}

// FIXME review this config
export const config = {
  /**
   * List of URLs that will go through the middleware (i.e., require authentication)
   * When users are not authenticated and try to access one of those paths, they are redirected to the signin page
   * WARNING: The rules below are evaluated at build time and cannot use variables for some reason
   * Make sure to keep those aligned with constants in constants.ts
   */
  matcher: [
    /*
     * Match all request paths except:
     * - _next/static (static files)
     * - _next/image (image optimization files)
     * - favicon.ico (favicon file)
     * - images - .svg, .png, .jpg, .jpeg, .gif, .webp
     * Feel free to modify this pattern to include more paths.
     */
    '/((?!_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|jpeg|gif|webp)$).*)',
    // FIXME are those still needed?
    // If those need to be protected, the layout.tsx in their folder could do the same as the one under app/app
    //'/account',
    // FIXME reimplement
    //'/communities/create',
  ],
};
