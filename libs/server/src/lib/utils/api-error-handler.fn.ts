import { NextApiRequest, NextApiResponse } from 'next';

/**
 * Api error handle
 * @param err
 * @param _req
 * @param res
 * @param _next
 */
export const apiErrorHandler = async (
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  err: any,
  _req: NextApiRequest,
  res: NextApiResponse,

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  _next: any,
): Promise<void> => {
  // FIXME improve
  console.log(err);
  res.status(500).end('Internal server error');
};
