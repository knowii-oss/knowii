'use client';

import type { Session } from '@supabase/auth-helpers-nextjs';
import { createContext, useContext, useState } from 'react';
import type { TypedSupabaseClient } from '@knowii/common';
import { createBrowserClient } from '../../../libs/client/src/lib/supabase/supabase-browser';

export type MaybeSupabaseSession = Session | null;

type SupabaseContext = {
  supabase: TypedSupabaseClient;
  session: MaybeSupabaseSession;
};

// @ts-ignore
const Context = createContext<SupabaseContext>();

export default function SupabaseProvider({ children, session }: { children: React.ReactNode; session: MaybeSupabaseSession }) {
  const [supabase] = useState(() => createBrowserClient());

  return (
    <Context.Provider value={{ supabase, session }}>
      <>{children}</>
    </Context.Provider>
  );
}

export const useSupabase = () => useContext(Context);
