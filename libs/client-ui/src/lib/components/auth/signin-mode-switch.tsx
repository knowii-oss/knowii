import { Box, Flex, useColorModeValue } from '@chakra-ui/react';
import { useMemo } from 'react';
import { useTranslations } from 'next-intl';

export enum SigninMode {
  Password = 'password',
  MagicLink = 'magic-link',
}

export interface SigninModeSwitchProps {
  activeMode: SigninMode;
  onChange: (mode: SigninMode) => void;
}

export function SigninModeSwitch({ activeMode, onChange }: SigninModeSwitchProps) {
  const t = useTranslations('signinModeSwitch');

  const modes = useMemo<Array<{ key: SigninMode; title: string }>>(
    () => [
      {
        key: SigninMode.MagicLink,
        title: t('magicLink'),
      },
      {
        key: SigninMode.Password,
        title: t('password'),
      },
    ],
    [t],
  );

  return (
    <Flex justify="center" borderBottom="2px" borderColor={useColorModeValue('blackAlpha.100', 'whiteAlpha.100')} w="full">
      {modes.map((mode) => (
        <Box
          key={mode.key}
          as="button"
          type="button"
          display="block"
          px={6}
          py={2}
          w="50%"
          mb="-2px"
          borderBottom="2px solid"
          borderColor={mode.key === activeMode ? 'primary.500' : 'transparent'}
          fontSize="sm"
          fontWeight={mode.key === activeMode ? 'bold' : 'normal'}
          color={mode.key === activeMode ? 'primary.500' : 'inherit'}
          onClick={() => onChange(mode.key)}
          cursor="pointer"
          _hover={{ textDecoration: 'none', color: mode.key === activeMode ? 'primary.500' : 'primary.600' }}
        >
          {mode.title}
        </Box>
      ))}
    </Flex>
  );
}

export default SigninModeSwitch;
