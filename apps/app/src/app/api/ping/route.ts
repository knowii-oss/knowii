import { NextResponse } from 'next/server';
import { z } from 'zod';
import { getLogger } from '../../utils/logging';

export const PingResponseSchema = z.object({
  pong: z.string(),
});

export type PingResponse = z.infer<typeof PingResponseSchema>;

// Force dynamic rendering: https://nextjs.org/docs/app/building-your-application/rendering/server-components#dynamic-rendering
export const dynamic = 'force-dynamic';

export async function GET(request: Request) {
  const logger = getLogger('api', request.url);
  logger.trace('Handling GET Ping request');

  const responseBody: PingResponse = {
    pong: 'pong',
  };

  return NextResponse.json(responseBody);
}
