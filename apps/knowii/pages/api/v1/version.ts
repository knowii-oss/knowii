import { NextApiRequest, NextApiResponse } from 'next';

// eslint-disable-next-line  @typescript-eslint/no-var-requires
const version = require('../../../../../package.json').version;

export interface VersionResponse {
  version: string;
}

export default async function handler(_req: NextApiRequest, res: NextApiResponse<VersionResponse>) {
  res.statusCode = 200;
  res.setHeader('Content-Type', 'application/json');
  res.end(JSON.stringify({ version }));
}
