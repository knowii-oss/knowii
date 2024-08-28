export function server(): string {
  return 'server';
}

// Supabase
export { createSupabaseServerSideClient } from './supabase/create-supabase-server-side-client.fn';
export { createSupabaseAdminServerSideClient } from './supabase/create-supabase-admin-server-side-client.fn';
export * from './supabase/get-internal-user-id-from-supabase-session.fn';
export * from './supabase/middleware';

// Utils
export * from './utils/errors';
