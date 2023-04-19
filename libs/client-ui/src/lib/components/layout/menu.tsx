import { Button, Stack, useColorModeValue } from '@chakra-ui/react';
import { useUser } from '@supabase/auth-helpers-react';
import Link from 'next/link';
import { useMobileBreakpoint } from './layout';
import { useTranslations } from 'next-intl';

export interface MenuProps {
  mobileMode: boolean;
}

export function Menu({ mobileMode }: MenuProps) {
  const t = useTranslations('menu');
  const user = useUser();
  const menuItemColor = useColorModeValue('blue.500', 'white');
  const isMobile = useMobileBreakpoint();
  const isHidden = isMobile !== mobileMode;

  const menuItems = [
    //
    // {
    //   label: t('features'),
    //   link: '/#features',
    // },
    // {
    //   label: t('pricing'),
    //   link: '/#pricing',
    // },
    {
      label: t('communities'),
      link: '/communities',
    },
    {
      label: t('blog'),
      link: '/blog',
    },
  ]
    // add dashboard link only if user is logged in
    .concat(user ? [{ label: t('dashboard'), link: '/app' }] : []);

  return (
    <Stack hidden={isHidden} direction={mobileMode ? 'column' : 'row'} spacing={4} align={mobileMode ? 'start' : 'center'}>
      {menuItems.map((item, i) => (
        <Button
          as={Link}
          key={i}
          href={item.link}
          fontSize={mobileMode ? 'lg' : 'base'}
          aria-label={''} // FIXME set aria label on all elements
          display="block"
          variant="ghost"
          fontWeight="normal"
          color={menuItemColor}
        >
          <div className="px-4 pt-4 pb-0">{item.label}</div>
        </Button>
      ))}
    </Stack>
  );
}

export default Menu;
