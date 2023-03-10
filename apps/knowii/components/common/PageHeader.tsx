import { Box, BoxProps, Container, ContainerProps, Heading, Text, useColorModeValue, VStack } from '@chakra-ui/react';
import { PropsWithChildren, useMemo } from 'react';

export default function PageHeader({
  title,
  description,
  align,
  containerMaxWidth,
  children,
  ...rest
}: PropsWithChildren<
  {
    title: string;
    description?: string;
    align?: 'start' | 'center' | 'end';
    containerMaxWidth?: ContainerProps['maxW'];
  } & BoxProps
>) {
  const bgColor = useColorModeValue('primary.50', 'gray.900');
  const textAlign = useMemo(() => (align === 'start' && 'left') || (align === 'end' && 'right') || 'center', [align]);

  return (
    <Box bg={bgColor} py={12} px={4} borderWidth="1px 0" borderColor={useColorModeValue('gray.100', 'gray.800')} {...rest}>
      <Container maxW={containerMaxWidth ?? '5xl'}>
        <VStack spacing={2} align={align ?? 'center'}>
          <Heading fontSize={{ base: '4xl', lg: '5xl' }} textAlign={textAlign}>
            {title}
          </Heading>
          {description && (
            <Text fontSize="lg" opacity={0.5} mt={2} textAlign={textAlign}>
              {description}
            </Text>
          )}
          {children && <Box>{children}</Box>}
        </VStack>
      </Container>
    </Box>
  );
}
