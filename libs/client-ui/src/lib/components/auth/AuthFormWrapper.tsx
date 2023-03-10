import { Box, Heading, Stack, Text, VStack } from '@chakra-ui/react';
import { PropsWithChildren, ReactElement } from 'react';

interface AuthFormWrapperProps {
  title: string;
  description?: string | ReactElement;
}

export function AuthFormWrapper({ title, description, children }: PropsWithChildren<AuthFormWrapperProps>) {
  //const { t } = useTranslation('auth');

  return (
    <VStack spacing={6} align="stretch">
      <Stack spacing={2}>
        <Heading fontSize="4xl">{title}</Heading>
        {description && <Text color="gray.500">{description}</Text>}
      </Stack>
      <Box>{children}</Box>
    </VStack>
  );
}

export default AuthFormWrapper;
