import { Box, Container, Flex, Heading, ListItem, Stack, UnorderedList, useColorModeValue, VStack } from '@chakra-ui/react';
import { FaCloud } from 'react-icons/fa';
import { useTranslations } from 'next-intl';
import { SectionHeadline } from './section-headline';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface FeaturesProps {}

export function Features(_props: FeaturesProps) {
  const t = useTranslations('features');

  const itemBorder = useColorModeValue('primary.100', 'gray.700');

  const featureItems = [
    {
      title: t('contentCuration.title'),
      icon: <FaCloud />,
      highlights: [t('contentCuration.highlights.1'), t('contentCuration.highlights.2'), t('contentCuration.highlights.3')],
    },
  ];

  return (
    <Box as="section" id="features" px={8} py={16}>
      <SectionHeadline title={t('title')} description={t('description')} />
      <Container maxW="3xl">
        <VStack spacing={4}>
          {featureItems.map((item, i) => (
            <Stack
              direction={{ base: 'column', md: 'row' }}
              spacing={6}
              key={i}
              w="full"
              p={8}
              border="2px solid"
              borderColor={itemBorder}
              rounded="3xl"
            >
              <Flex align="start" justify="center" fontSize="5xl" color="primary.500" w={20} rounded="3xl" flexShrink={0}>
                {item.icon}
              </Flex>
              <Box flex={1}>
                <Heading fontSize="xl" mb={2}>
                  {item.title}
                </Heading>
                <UnorderedList opacity={0.5} spacing={1}>
                  {item.highlights.map((highlight, k) => (
                    <ListItem key={k}>{highlight}</ListItem>
                  ))}
                </UnorderedList>
              </Box>
            </Stack>
          ))}
        </VStack>
      </Container>
    </Box>
  );
}

export default Features;
