import { Button, Modal, ModalBody, ModalCloseButton, ModalContent, ModalFooter, ModalHeader, ModalOverlay, Text } from '@chakra-ui/react';
import { useTranslation } from 'next-i18next';

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
  const { t } = useTranslation('app');

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
            {t('confirmModal.cancel')}
          </Button>
          <Button colorScheme={isDelete ? 'red' : 'primary'} onClick={() => onClose(true)} ml={3}>
            {t('confirmModal.confirm')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default ConfirmModal;
