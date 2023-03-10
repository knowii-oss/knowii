import { Flex, Spinner } from '@chakra-ui/react';

export interface LoaderProps {
  color?: string;
}

export function Loader({ color = 'primary.300' }: LoaderProps) {
  return (
    <Flex justify="center" py={6}>
      <Spinner size="lg" color={color} mx="auto" />
    </Flex>
  );
}

export default Loader;
