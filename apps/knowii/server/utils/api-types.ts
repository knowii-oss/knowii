import type { NextApiResponse, NextApiRequest } from 'next';
import type { RequestHandler as NextConnectRequestHandler, Middleware as NextConnectMiddleware } from 'next-connect';

/**
 * A middleware
 */
export type Middleware<
  ReturnType = undefined,
  // eslint-disable-next-line @typescript-eslint/ban-types
  ExtendedApiRequest = {}
> = NextConnectMiddleware<NextApiRequest & ExtendedApiRequest, NextApiResponse<ReturnType>>;

/**
 * A request handler
 */
export type NextRequestHandler<
  ReturnType = undefined,
  // eslint-disable-next-line @typescript-eslint/ban-types
  ExtendedApiRequest = {}
> = NextConnectRequestHandler<
  // allows overwriting e.g. query
  NextApiRequest & ExtendedApiRequest,
  NextApiResponse<ReturnType>
>;
