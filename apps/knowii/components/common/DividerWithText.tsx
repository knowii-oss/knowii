import { Divider, Flex, Text } from '@chakra-ui/react';

interface Props {
  text: string;
}

function DividerWithText({ text }: Props) {
  return (
    <Flex pos="relative" align="center" justify="start" my={6}>
      <Divider position="absolute" top={5} zIndex={10} />
      <Text as="span" display="inline-block" p={3} pl={0} lineHeight={1} zIndex={20} pos="relative" fontWeight="bold" fontSize="sm">
        {text}
      </Text>
    </Flex>
  );
}
export default DividerWithText;
