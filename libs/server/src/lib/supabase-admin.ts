import { createClient } from '@supabase/supabase-js';
import { Database } from '@knowii/common';

/*
  Create an admin client for supabase to manage the database
*/
export const supabaseAdmin = createClient<Database>(
  process.env.NEXT_PUBLIC_SUPABASE_URL || '',
  process.env.SUPABASE_SERVICE_ROLE_KEY || '',
);
