import { z } from 'zod';
import { procedure, router } from './trpc-server';

export const trpcAppRouter = router({
  hello: procedure
    .input(
      z.object({
        text: z.string(),
      }),
    )
    .query((opts) => {
      return {
        hello: `Hello ${opts.input.text}`,
      };
    }),
});

// export type definition of API
export type AppRouter = typeof trpcAppRouter;
