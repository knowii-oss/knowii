export * from './auth';
export * from './i18n';
export * from './subscriptions';

// Load general config from environment variables
// Also validates that the variables are actually defined

// FIXME breaks tests
// function throwEnvError(key: string) {
//   throw new Error(`${key} environment variable is not set.`);
// }
//
// export const SUPABASE_URL = process.env.NEXT_PUBLIC_SUPABASE_URL as string;
// if (!SUPABASE_URL) {
//   throwEnvError('NEXT_PUBLIC_SUPABASE_URL');
// }
//
// export const SUPABASE_ANON_KEY = process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY as string;
// if (!SUPABASE_ANON_KEY) {
//   throwEnvError('NEXT_PUBLIC_SUPABASE_ANON_KEY');
// }
// export const CONFIGURED_AUTH_PROVIDERS = process.env.CONFIGURED_AUTH_PROVIDERS as string;
// if (!CONFIGURED_AUTH_PROVIDERS) {
//   throwEnvError('CONFIGURED_AUTH_PROVIDERS');
// }
// TODO add other env vars to check
