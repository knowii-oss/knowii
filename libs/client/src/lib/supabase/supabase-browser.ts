import { createBrowserSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { Database } from '@knowii/common';

export const createBrowserClient = () => createBrowserSupabaseClient<Database>();
