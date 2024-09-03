import { Container, Heading, Text, VStack } from '@chakra-ui/react';

interface SectionHeadlineProps {
  title: string;
  description?: string;
}

// FIXME add doc and stories
export function SectionHeadline({ title, description }: SectionHeadlineProps) {
  return (
    <Container maxW="5xl" textAlign={{ base: 'left', md: 'center' }} mb={16}>
      <VStack spacing={2} justify="start" align={{ base: 'start', md: 'center' }}>
        <Heading maxW="2xl" fontSize="4xl" lineHeight="shorter">
          {title}
        </Heading>
        {description && (
          <Text fontSize="lg" maxW="2xl">
            {description}
          </Text>
        )}
      </VStack>
    </Container>
  );
}

export default SectionHeadline;
