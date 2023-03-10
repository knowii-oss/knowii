import { Flex, Spinner } from '@chakra-ui/react';

interface Props {
  color?: string;
}

function Loader({ color = 'primary.300' }: Props) {
  return (
    <Flex justify="center" py={6}>
      <Spinner size="lg" color={color} mx="auto" />
    </Flex>
  );
}

export default Loader;
