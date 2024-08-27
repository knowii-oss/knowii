import { createBrowserClient } from '@supabase/ssr';

/**
 * Create a Supabase browser client
 */
export const createSupabaseBrowserClient = () =>
  createBrowserClient(process.env.NEXT_PUBLIC_SUPABASE_URL || '', process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY || '');
