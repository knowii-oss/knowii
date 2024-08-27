import { createClient } from '@supabase/supabase-js';
import { Database } from '@knowii/common';

const supabaseUrl = process.env.NEXT_PUBLIC_SUPABASE_URL || '';
const supabaseServiceRoleKey = process.env.SUPABASE_SERVICE_ROLE_KEY || '';

/*
  Create an admin client for supabase to manage the database
*/
export const supabaseAdmin = createClient<Database>(supabaseUrl, supabaseServiceRoleKey);
