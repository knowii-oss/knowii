import { Database } from '@knowii/common';
import type { SupabaseClient } from '@supabase/auth-helpers-nextjs';

export type TypedSupabaseClient = SupabaseClient<Database>;
