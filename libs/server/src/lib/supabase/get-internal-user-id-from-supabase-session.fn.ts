import { Session } from '@supabase/supabase-js';

/**
 * WARNING: this MUST remain in line with the JSON field name we set in supabase-db-seed.sql
 */
const fieldName = 'knowii_user_id_internal';

/**
 * Extract the internal user id from the Supabase session
 * Note that this works because we inject the internal user id upon registration via the function invoked when a new user is created
 * We add the id to a field (name above) of raw_user_meta_data
 * @param session
 */
export function getInternalUserIdFromSupabaseSession(session: Session): string {
  return session.user.user_metadata[fieldName];
}
