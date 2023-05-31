import { createPagesBrowserClient } from '@supabase/auth-helpers-nextjs';
import { Database } from '@knowii/common';

export const createBrowserClient = () => createPagesBrowserClient<Database>();
