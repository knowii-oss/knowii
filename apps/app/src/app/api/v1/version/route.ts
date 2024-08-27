import { NextResponse } from 'next/server';
import { z } from 'zod';
import { getLogger } from '@knowii/common';

// eslint-disable-next-line  @typescript-eslint/no-var-requires
const version = require('../../../../../../../package.json').version;

export const VersionResponseSchema = z.object({
  version: z.string(),
});

export type VersionResponse = z.infer<typeof VersionResponseSchema>;

// Force dynamic rendering: https://nextjs.org/docs/app/building-your-application/rendering/server-components#dynamic-rendering
export const dynamic = 'force-dynamic';

export async function GET(request: Request) {
  const logger = getLogger('utils', request.url);
  logger.trace('Handling GET Version request');

  const responseBody: VersionResponse = {
    version,
  };

  return NextResponse.json(responseBody);
}
