import { Box, Heading, useColorModeValue } from '@chakra-ui/react';
import { PropsWithChildren } from 'react';

export interface SectionProps {
  title: string;
}

export function Section({ title, children }: PropsWithChildren<SectionProps>) {
  return (
    <Box border="2px solid" borderColor={useColorModeValue('gray.100', 'gray.700')} p={6} rounded="lg">
      <Heading as="h2" fontSize="3xl" mb={4}>
        {title}
      </Heading>

      {children}
    </Box>
  );
}
export default Section;
