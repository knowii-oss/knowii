# TODO

## Migration to Next.js 13 app folder

- Migrate to use new Supabase approach for next.js 13: https://supabase.com/blog/fetching-and-caching-supabase-data-in-next-js-server-components
- Remove encoding devDependency once this is fixed: https://github.com/supabase/supabase-js/issues/612
- Wrap Chakra components with "use client" to be able to use those in server components: https://stackoverflow.com/questions/75591291/how-to-use-server-components-in-nextjs-13-with-chakra-ui
- Follow: https://github.com/supabase/auth-helpers/tree/main/examples/nextjs-server-components
  -ColorModeScript should probably move to chakra-providers
