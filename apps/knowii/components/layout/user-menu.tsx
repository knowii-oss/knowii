import { IconButton, Menu, MenuButton, MenuGroup, MenuItem, MenuList } from '@chakra-ui/react';
import { useSupabaseClient } from '@supabase/auth-helpers-react';
import Link from 'next/link';
import { useRouter } from 'next/router';
import { useCallback } from 'react';
import { FaSignOutAlt, FaUser } from 'react-icons/fa';
import { useTranslations } from 'next-intl';
import { useUserName } from '@knowii/client';
import { ACCOUNT_URL, HOME_URL } from '@knowii/common';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface UserMenuProps {}

export function UserMenu(_props: UserMenuProps) {
  const t = useTranslations('userMenu');

  const supabaseClient = useSupabaseClient();
  const router = useRouter();
  const userName = useUserName();

  const signOut = useCallback(async () => {
    await router.push(HOME_URL);
    supabaseClient.auth.signOut();
  }, [router, supabaseClient.auth]);

  return (
    <Menu placement="bottom-end">
      <MenuButton as={IconButton} size="sm" aria-label="Options" icon={<FaUser />} rounded="full" colorScheme="primary" />
      <MenuList>
        <MenuGroup title={t('message', { user: userName })}>
          <MenuItem as={Link} href={ACCOUNT_URL} icon={<FaUser />}>
            {t('account')}
          </MenuItem>

          <MenuItem icon={<FaSignOutAlt />} onClick={() => signOut?.()}>
            {t('signOut')}
          </MenuItem>
        </MenuGroup>
      </MenuList>
    </Menu>
  );
}

export default UserMenu;
