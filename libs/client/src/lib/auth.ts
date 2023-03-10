import { useUser } from '@supabase/auth-helpers-react';
import { useRouter } from 'next/router';
import { useMemo } from 'react';
import { redirectPath } from '@knowii/common';

export function useUserName() {
  const user = useUser();
  const userName = useMemo(() => {
    return user?.user_metadata.full_name || user?.email;
  }, [user]);

  return userName;
}

export function useAuthRedirectUrl(path = redirectPath) {
  const { locale, defaultLocale } = useRouter();

  const baseUrl = useMemo(() => (typeof window !== 'undefined' ? window.location.origin : '/'), []);
  const localePath = useMemo(() => (locale === defaultLocale ? '' : `/${locale}`), [locale, defaultLocale]);
  const formattedPath = useMemo(() => (path.startsWith('/') ? path : `/${path}`), [path]);

  return useMemo(() => `${baseUrl}${localePath}${formattedPath}`, [localePath, formattedPath, baseUrl]);
}
