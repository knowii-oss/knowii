import { NextApiRequest, NextApiResponse } from 'next';
import { NextRequestHandler } from '@knowii/server';

export interface PingResponse {
  pong: string;
}

const handler: NextRequestHandler<PingResponse> = (_req: NextApiRequest, res: NextApiResponse) => {
  res.statusCode = 200;
  res.setHeader('Content-Type', 'application/json');
  res.end(JSON.stringify({ pong: 'pong' }));
};

export default handler;
