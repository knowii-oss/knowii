import { PrismaClient } from '@prisma/client';

const prismaClientPropertyName = `__prevent-name-collision__prisma`;
type GlobalThisWithPrismaClient = typeof globalThis & {
  [prismaClientPropertyName]: PrismaClient;
};

/**
 * Unique instance of the Prisma client.
 * It includes a connection pooling mechanism, so we need to avoid creating additional instances!
 */
export const prisma = getPrismaClient();

/**
 * Get a Prisma client instance
 * Avoid creating new instances if not needed.
 * Enable query logging in dev
 */
function getPrismaClient() {
  if (process.env.NODE_ENV === `production`) {
    return new PrismaClient();
  } else {
    const newGlobalThis = globalThis as GlobalThisWithPrismaClient;
    if (!newGlobalThis[prismaClientPropertyName]) {
      newGlobalThis[prismaClientPropertyName] = new PrismaClient({
        log: ['query'],
      });
    }
    return newGlobalThis[prismaClientPropertyName];
  }
}
