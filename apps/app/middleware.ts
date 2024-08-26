import { NextRequest, NextResponse } from 'next/server';
import { getLogger } from '@knowii/common';

export default function middleware(request: NextRequest) {
  const logger = getLogger('utils', request.url);

  const response = NextResponse.next();
  logger.info('Request', request);
  logger.info('Response', response);

  return response;
}
