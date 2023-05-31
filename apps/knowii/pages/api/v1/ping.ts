import { NextApiRequest, NextApiResponse } from 'next';
import { getLogger } from '@knowii/common';

export interface PingResponse {
  pong: string;
}

export default async function handler(req: NextApiRequest, res: NextApiResponse<PingResponse>) {
  const logger = getLogger('utils', req.url!);

  logger.info('Handling request');

  res.status(200).json({ pong: 'pong' });
}
