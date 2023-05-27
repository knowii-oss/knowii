import pino from 'pino';

export function getLogger(name: string) {
  const retVal = pino({
    browser: {},
    level: 'debug',
    transport: {
      target: 'pino-pretty',
      options: {
        colorize: true,
      },
    },
    base: {
      env: process.env.NODE_ENV,
      revision: process.env.VERCEL_GITHUB_COMMIT_SHA,
    },
    name: name ? name : undefined,
  });

  return retVal;
}
