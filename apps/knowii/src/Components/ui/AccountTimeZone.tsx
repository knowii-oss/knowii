import { createContext, useContext } from 'react';

const AccountTimeZoneContext = createContext<string | null>(null);

/**
 * The time zone of the account (#1476), null when it is not known: the layout gives it, so that every time of the page shows
 * its full date and hour in the zone of the member
 */
export const AccountTimeZoneProvider = AccountTimeZoneContext.Provider;

export function useAccountTimeZone(): string | null {
  return useContext(AccountTimeZoneContext);
}
