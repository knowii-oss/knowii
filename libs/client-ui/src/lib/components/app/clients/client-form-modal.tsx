import {
  Button,
  FormControl,
  FormLabel,
  Input,
  Modal,
  ModalBody,
  ModalCloseButton,
  ModalContent,
  ModalHeader,
  ModalOverlay,
  useColorModeValue,
  useToast,
  VStack,
} from '@chakra-ui/react';
import { useSupabaseClient } from '@supabase/auth-helpers-react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'next-i18next';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { Database, I18N_TRANSLATIONS_APP } from '@knowii/common';
import { Client } from './clients-list';

export interface ClientFormModalProps {
  client: null | Client;
  isOpen: boolean;
  onClose: () => void;
}

/**
 * Show the client modal
 * @constructor
 */
export function ClientFormModal({ client, isOpen, onClose }: ClientFormModalProps) {
  const supabaseClient = useSupabaseClient<Database>();
  const queryClient = useQueryClient();
  const headerBg = useColorModeValue('gray.50', 'gray.600');
  const { t } = useTranslation(I18N_TRANSLATIONS_APP);
  const toast = useToast();
  const {
    register,
    handleSubmit,
    reset,
    setValue,
    formState: { isSubmitting },
  } = useForm<{ name: string; email: string; phone: string }>();

  // when the modal is closed, reset the form
  useEffect(() => {
    if (!client) {
      reset();
      return;
    }

    setValue('name', client.name);
    setValue('email', client.email);
    setValue('phone', client.phone);
  }, [client]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!isOpen) {
      reset();
    }
  }, [isOpen, reset]);

  const upsertMutation = useMutation(
    async (client: Database['public']['Tables']['clients']['Insert']) => {
      const { error } = await supabaseClient.from('clients').upsert(client);

      if (error) {
        throw new Error('Could not upsert client');
      }
    },
    {
      onError: () => {
        toast({
          status: 'error',
          position: 'top',
          description: t('clients.form.errorMessage'),
        });

        throw new Error('Could not upsert client');
      },
      onSuccess: () => {
        toast({
          status: 'success',
          position: 'top',
          description: t('clients.form.successMessage'),
        });

        // Refetch clients
        queryClient.invalidateQueries(['clients']);

        onClose();
      },
    },
  );

  const onSubmit = handleSubmit(async (values) => {
    await upsertMutation.mutateAsync({
      ...values,
      id: (client?.id || undefined) as any, // FIXME Verify that this works
      user_id: (client?.user_id || undefined) as any, // FIXME Verify that this works
    });
  });

  return (
    <Modal isOpen={isOpen} onClose={onClose} isCentered scrollBehavior="inside" closeOnEsc={false} closeOnOverlayClick={false}>
      <ModalOverlay />
      <ModalContent>
        <ModalHeader bg={headerBg} roundedTop="lg" px={8}>
          {client ? t('clients.form.edit.title') : t('clients.form.add.title')}
        </ModalHeader>
        <ModalCloseButton top={4} right={6} />
        <ModalBody p={8}>
          <form onSubmit={(e) => onSubmit(e).catch(() => {})}>
            <VStack align="stretch" spacing={6}>
              {/* Name field */}
              <FormControl isRequired>
                <FormLabel>{t('clients.form.controls.name')}</FormLabel>
                <Input type="text" {...register('name', { required: true })} />
              </FormControl>

              {/* Email field */}
              <FormControl isRequired>
                <FormLabel>{t('clients.form.controls.email')}</FormLabel>
                <Input type="text" {...register('email', { required: true })} />
              </FormControl>

              {/* Phone field */}
              <FormControl isRequired>
                <FormLabel>{t('clients.form.controls.phone')}</FormLabel>
                <Input type="text" {...register('phone', { required: true })} />
              </FormControl>

              <Button type="submit" colorScheme="primary" isLoading={isSubmitting}>
                {t('clients.form.submitButton')}
              </Button>
            </VStack>
          </form>
        </ModalBody>
      </ModalContent>
    </Modal>
  );
}

export default ClientFormModal;
