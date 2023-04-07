import { Button } from '@chakra-ui/react';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import { Section } from '../layout/section';
import { RESET_PASSWORD_URL } from '@knowii/common';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface PasswordProps {}

export function Password(_props: PasswordProps) {
  const t = useTranslations('password');

  return (
    <Section title={t('password')}>
      <Button as={Link} href={RESET_PASSWORD_URL} variant="outline" colorScheme="primary">
        {t('changeMyPassword')} &rarr;
      </Button>
    </Section>
  );
}

export default Password;
