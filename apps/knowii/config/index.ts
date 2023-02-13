// Load general config from enviorment variables

function throwEnvError(key: string) {
  throw new Error(`${key} environment variable is not set.`);
}

export const SUPABASE_URL = process.env.NEXT_PUBLIC_SUPABASE_URL as string;
if (!SUPABASE_URL) throwEnvError('NEXT_PUBLIC_SUPABASE_URL');

export const SUPABASE_ANON_KEY = process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY as string;
if (!SUPABASE_ANON_KEY) throwEnvError('NEXT_PUBLIC_SUPABASE_ANON_KEY');
