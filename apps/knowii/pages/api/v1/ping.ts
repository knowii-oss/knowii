import { NextApiRequest, NextApiResponse } from 'next';
import { NextRequestHandler } from '@knowii/server';
import { getLogger } from '@knowii/common';

export interface PingResponse {
  pong: string;
}

const handler: NextRequestHandler<PingResponse> = async (req: NextApiRequest, res: NextApiResponse<PingResponse>) => {
  const logger = getLogger('utils', req.url!);

  logger.info('Handling request');

  res.status(200).json({ pong: 'pong' });
};

export default handler;
