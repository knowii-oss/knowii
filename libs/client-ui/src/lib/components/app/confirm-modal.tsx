import { Button, Modal, ModalBody, ModalCloseButton, ModalContent, ModalFooter, ModalHeader, ModalOverlay, Text } from '@chakra-ui/react';
import { useTranslations } from 'next-intl';

export interface ConfirmModalProps {
  title: string;
  description: string;
  isDelete: boolean;
  isOpen: boolean;
  onClose: (confirmed: boolean) => void;
}

/**
 * Show the confirm modal
 * @constructor
 */
export function ConfirmModal({ title, description, isDelete, isOpen, onClose }: ConfirmModalProps) {
  const t = useTranslations('confirmModal');

  return (
    <Modal isOpen={isOpen} onClose={() => onClose(false)} isCentered scrollBehavior="inside" closeOnEsc={false} closeOnOverlayClick={false}>
      <ModalOverlay />
      <ModalContent>
        <ModalHeader>{title}</ModalHeader>
        <ModalCloseButton />
        <ModalBody>
          <Text>{description}</Text>
        </ModalBody>
        <ModalFooter>
          <Button variant="ghost" colorScheme="gray" onClick={() => onClose(false)}>
            {t('cancel')}
          </Button>
          <Button colorScheme={isDelete ? 'red' : 'primary'} onClick={() => onClose(true)} ml={3}>
            {t('confirm')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default ConfirmModal;
