import { Drawer, DrawerBody, DrawerCloseButton, DrawerContent, VStack } from '@chakra-ui/react';
import { useRouter } from 'next/router';
import { useEffect } from 'react';
import { Menu } from './menu';

export interface MobileDrawerMenuProps {
  isOpen: boolean;
  onClose: () => void;
}

export function MobileDrawerMenu(props: MobileDrawerMenuProps) {
  const router = useRouter();

  useEffect(() => props.onClose(), [router.asPath]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <Drawer isOpen={props.isOpen} placement="right" onClose={props.onClose} size="xs">
      <DrawerContent>
        <DrawerCloseButton rounded="xl" opacity={0.5} />

        <DrawerBody px={8} py={12}>
          <VStack align="start" spacing={6}>
            <Menu mobileMode />
          </VStack>
        </DrawerBody>
      </DrawerContent>
    </Drawer>
  );
}

export default MobileDrawerMenu;
