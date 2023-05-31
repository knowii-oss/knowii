import { Button } from '@chakra-ui/react';
import { useUser } from '@supabase/auth-helpers-react';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import { APP_BASE_URL, SIGN_UP_URL } from '@knowii/common';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface HeroProps {}

export function Hero(_props: HeroProps) {
  const t = useTranslations('hero');
  const user = useUser();

  return (
    <div className="flex flex-col text-center px-8 py-36 bg-primary-500 dark:bg-steel-500 gap-2">
      <h1 className="text-white">{t('title')}</h1>
      <h2 className="text-white">{t('description')}</h2>

      <div className="flex flex-col md:flex-row gap-3 mt-5 justify-center">
        <Button as={Link} href={user ? APP_BASE_URL : SIGN_UP_URL} size="lg" colorScheme="primary">
          {user ? t('dashboardButton') : t('signupButton')} &rarr;
        </Button>
      </div>
    </div>
  );
}

export default Hero;
