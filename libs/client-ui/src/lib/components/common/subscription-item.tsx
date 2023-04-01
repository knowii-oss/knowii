import { Box, Button, Heading, ListItem, Text, UnorderedList, useColorModeValue, VStack } from '@chakra-ui/react';
import { useRouter } from 'next/router';
import { useMemo } from 'react';
import { useTranslations } from 'next-intl';
import { defaultCurrency, formatPrice, localeCurrencies, SubscriptionPlan } from '@knowii/common';
import { useSubscriptionActions } from '@knowii/client';

export interface SubscriptionItemProps {
  plan: SubscriptionPlan;
  billingInterval: string;
  isHighlighted: boolean;
  hideSubscribeButton: boolean;
}

export function SubscriptionItem({ plan, billingInterval, isHighlighted, hideSubscribeButton }: SubscriptionItemProps) {
  const router = useRouter();

  const t = useTranslations('subscriptionItem');

  const { subscribe, loading } = useSubscriptionActions();
  const bgColor = useColorModeValue('primary.50', 'gray.900');
  const highlightColor = useColorModeValue('primary.500', 'primary.400');
  const localeCurrency = useMemo(
    () => ((router.locale && localeCurrencies[router.locale as keyof typeof localeCurrencies]) || defaultCurrency).toLowerCase(),
    [router.locale],
  );

  const price = useMemo(() => {
    const pricesWithBillingInterval = plan.prices.filter((price) => price.interval === billingInterval);

    const priceWithLocaleCurrency = pricesWithBillingInterval.find((price) => price.currency.toLowerCase() === localeCurrency);

    return priceWithLocaleCurrency || pricesWithBillingInterval[0];
  }, [plan, billingInterval, localeCurrency]);

  const formattedPrice = useMemo(
    () =>
      formatPrice({
        locale: router.locale,
        currency: price?.currency,
        amount: price?.unit_amount ?? 0,
      }),
    [price, router.locale],
  );

  return (
    <Box p={8} rounded="lg" bg={bgColor} border="4px solid" borderColor={isHighlighted ? highlightColor : 'transparent'}>
      <VStack spacing={4} align="stretch" justify="space-between" h="full">
        <VStack spacing={3} align="stretch">
          <Heading as="h3">{plan.name}</Heading>
          <Text fontSize="lg">{plan.description}</Text>
          {plan.features && (
            <UnorderedList opacity={0.75} pl={5} spacing={1}>
              {plan.features?.map((feature, i) => (
                <ListItem key={`plan-${plan.id}-feature-${i}`}>{feature}</ListItem>
              ))}
            </UnorderedList>
          )}
        </VStack>

        {price && (
          <VStack spacing={4} align="stretch">
            <Heading as="h4" fontSize="3xl" color={highlightColor}>
              {formattedPrice}{' '}
              <Text as="small" color="inherit" opacity={0.75} fontSize="lg" fontWeight="normal">
                {/* Make sure this works. It was using a parameterized translation key before! */}/{' '}
                {price.interval_count && price.interval_count > 1 ? price.interval_count : ''} {billingInterval}
              </Text>
            </Heading>
            {!hideSubscribeButton && (
              <Button colorScheme="primary" w="full" size="lg" onClick={() => subscribe(price.id)} isLoading={loading}>
                {t('subscribeButton')}
              </Button>
            )}
          </VStack>
        )}
      </VStack>
    </Box>
  );
}

export default SubscriptionItem;
