import { type NextRequest, NextResponse } from 'next/server';
import { getLogger } from '@knowii/common';
import { updateSession } from '@knowii/server';

export default async function middleware(request: NextRequest) {
  const logger = getLogger('utils', request.url);

  await updateSession(request);

  const response = NextResponse.next();
  logger.info('Request', request);
  logger.info('Response', response);

  return response;
}

export const config = {
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
  ],
};
