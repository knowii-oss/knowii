import type { SupabaseClient } from '@supabase/auth-helpers-nextjs';
import { Database } from './generated';

export type TypedSupabaseClient = SupabaseClient<Database>;
