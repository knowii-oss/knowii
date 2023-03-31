import { NextApiRequest, NextApiResponse } from 'next';
import { NextRequestHandler } from '@knowii/server';

// eslint-disable-next-line  @typescript-eslint/no-var-requires
const version = require('../../../../../package.json').version;

export interface VersionResponse {
  version: string;
}

const handler: NextRequestHandler<VersionResponse> = (_req: NextApiRequest, res: NextApiResponse) => {
  res.statusCode = 200;
  res.setHeader('Content-Type', 'application/json');
  res.end(JSON.stringify({ version }));
};

export default handler;
