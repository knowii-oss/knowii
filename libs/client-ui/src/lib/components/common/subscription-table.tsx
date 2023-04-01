import { SimpleGrid, Tab, TabList, Tabs, useColorModeValue, VStack } from '@chakra-ui/react';
import { useState } from 'react';
import { recommendedPlanId } from '@knowii/common';
import { SubscriptionItem } from './subscription-item';
import UserSubscriptionAlert from './user-subscription-alert';
import { useSubscriptionPlans, useUserSubscriptions } from '@knowii/client';
import { useTranslations } from 'next-intl';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface SubscriptionTableProps {}

export function SubscriptionTable(_props: SubscriptionTableProps) {
  const t = useTranslations('subscriptionTable');

  const [billingInterval, setBillingInterval] = useState<'month' | 'year'>('month');
  const { plans } = useSubscriptionPlans();
  const { currentlySubscribedPlan } = useUserSubscriptions();
  const bgColor = useColorModeValue('primary.50', 'gray.900');

  return (
    <VStack align="stretch" gap={6} mt={-6}>
      <Tabs
        onChange={(index) => setBillingInterval(index === 0 ? 'month' : 'year')}
        variant="soft-rounded"
        colorScheme="primary"
        bg={bgColor}
        p={1}
        mx="auto"
        rounded="full"
      >
        <TabList justifyContent="center">
          <Tab>{t('intervals.monthly')}</Tab>
          <Tab>{t('intervals.yearly')}</Tab>
        </TabList>
      </Tabs>

      {plans && (
        <SimpleGrid columns={{ base: 1, md: plans.length }} gap={4} alignItems="start">
          {plans.map((plan) => (
            <SubscriptionItem
              key={plan.id}
              plan={plan}
              billingInterval={billingInterval === 'year' ? t('intervals.yearly') : t('intervals.monthly')}
              isHighlighted={recommendedPlanId === plan.id}
              hideSubscribeButton={currentlySubscribedPlan !== null}
            />
          ))}
        </SimpleGrid>
      )}

      {currentlySubscribedPlan && <UserSubscriptionAlert />}
    </VStack>
  );
}

export default SubscriptionTable;
