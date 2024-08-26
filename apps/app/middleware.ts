import { NextRequest, NextResponse } from 'next/server';
import { getLogger } from './src/app/utils/logging';

export default function middleware(request: NextRequest) {
  const logger = getLogger('utils', request.url);

  const response = NextResponse.next();
  logger.info('Request', request);
  logger.info('Response', response);

  return response;
}
