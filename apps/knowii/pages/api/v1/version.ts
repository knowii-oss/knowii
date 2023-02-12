import { version } from '../../../../../package.json';
import { NextApiRequest, NextApiResponse } from 'next';
import { NextRequestHandler } from '../../../server/utils/api-types';

export interface VersionResponse {
  version: string;
}

const handler: NextRequestHandler<VersionResponse> = (_req: NextApiRequest, res: NextApiResponse) => {
  res.statusCode = 200;
  res.setHeader('Content-Type', 'application/json');
  res.end(JSON.stringify({ version }));
};

export default handler;
