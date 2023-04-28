import * as trpcNext from '@trpc/server/adapters/next';
import { trpcAppRouter } from '@knowii/server';

// export API handler
// @see https://trpc.io/docs/api-handler
export default trpcNext.createNextApiHandler({
  router: trpcAppRouter,
  createContext: () => ({}),
});
