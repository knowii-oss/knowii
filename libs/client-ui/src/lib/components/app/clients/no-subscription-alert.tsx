import { Alert, Button, Text, VStack } from '@chakra-ui/react';
import { useTranslation } from 'next-i18next';
import Link from 'next/link';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface NoSubscriptionAlertProps {}

/**
 * Show an alert when the user has no active subscription
 * @constructor
 */
export function NoSubscriptionAlert(_props: NoSubscriptionAlertProps) {
  const { t } = useTranslation('app');
  return (
    <Alert p={6} variant="left-accent" colorScheme="primary" rounded="md" mb={8}>
      <VStack align="start" gap={3}>
        <Text>{t('noSubscriptionAlert.message')}</Text>

        <Button as={Link} href="/#pricing" colorScheme="primary">
          {t('noSubscriptionAlert.upgradeButton')}
        </Button>
      </VStack>
    </Alert>
  );
}

export default NoSubscriptionAlert;
