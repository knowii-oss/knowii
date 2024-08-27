export function server(): string {
  return 'server';
}

// Supabase
export * from './supabase/supabase-admin';
export * from './supabase/get-internal-user-id-from-supabase-session.fn';

// Utils
export * from './utils/errors';
