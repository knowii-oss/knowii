import {
  Box,
  Button,
  Heading,
  HStack,
  IconButton,
  Skeleton,
  Stack,
  Table,
  Tbody,
  Td,
  Text,
  Th,
  Thead,
  Tooltip,
  Tr,
  useColorModeValue,
  useDisclosure,
  useToast,
  VStack,
} from '@chakra-ui/react';
import { useSupabaseClient } from '@supabase/auth-helpers-react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'next-i18next';
import Link from 'next/link';
import { useCallback, useState } from 'react';
import { FaEdit, FaTrash, FaUserPlus } from 'react-icons/fa';
import { useUserPermissions } from '@knowii/client';
import { Database, I18N_TRANSLATIONS_APP } from '@knowii/common';
import { Loader } from '../../common/loader';
import { ConfirmModal } from '../confirm-modal';
import { ClientFormModal } from './client-form-modal';
import { NoSubscriptionAlert } from './no-subscription-alert';

type Client = Database['public']['Tables']['clients']['Row'];

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface ClientsListProps {}

/**
 * Clients list
 * @constructor
 */
export function ClientsList(_props: ClientsListProps) {
  const supabaseClient = useSupabaseClient<Database>();
  const queryClient = useQueryClient();
  const { t } = useTranslation(I18N_TRANSLATIONS_APP);
  const toast = useToast();
  const { isUserSubscribed, loading: loadingPermissions } = useUserPermissions();
  const formModal = useDisclosure();
  const confirmModal = useDisclosure();
  const [selectedClient, setSelectedClient] = useState<null | Client>(null);
  const { data: clients, isLoading: loadingClients } = useQuery(['clients'], async () => {
    const { data: clients, error } = await supabaseClient.from('clients').select('*');
    if (error) throw new Error('Failed to fetch clients');
    return clients;
  });
  const tableBg = useColorModeValue('white', 'gray.700');
  const tableBorderColor = useColorModeValue('gray.100', 'gray.600');

  const openClientForm = useCallback(
    (client: Client | null) => {
      setSelectedClient(client);
      formModal.onOpen();
    },
    [formModal, setSelectedClient],
  );

  const openDeleteConfirm = useCallback(
    (client: Client | null) => {
      setSelectedClient(client);
      confirmModal.onOpen();
    },
    [confirmModal, setSelectedClient],
  );

  const deleteClientMutation = useMutation(
    async (id: string) => {
      if (!id) return;
      const { error } = await supabaseClient.from('clients').delete().eq('id', id);
      if (error) throw new Error('Could not delete client');
    },
    {
      // reset client after request
      onSettled: () => setSelectedClient(null),
      // show toast on error
      onError: () =>
        toast({
          status: 'error',
          title: t('deleteClient.errorMessage'),
        }),
      // show toast on success and refetch clients
      onSuccess: () => {
        toast({
          status: 'success',
          title: t('deleteClient.successMessage'),
        });
        queryClient.invalidateQueries(['clients']);
      },
    },
  );

  return loadingPermissions ? (
    <Loader />
  ) : (
    <>
      {!isUserSubscribed && !!clients?.length && <NoSubscriptionAlert />}
      <HStack mb={4} justify="space-between">
        <Heading fontSize="2xl">{t('clients.title')}</Heading>

        <Tooltip label={!isUserSubscribed && clients?.length ? t('clients.upgradeToCreateMore') : undefined}>
          <Button
            size="sm"
            colorScheme="primary"
            isDisabled={!isUserSubscribed && !!clients?.length}
            leftIcon={<FaUserPlus />}
            onClick={() => openClientForm(null)}
          >
            {t('clients.createButton')}
          </Button>
        </Tooltip>
      </HStack>
      <Box bg={tableBg} rounded="xl" border="1px solid " borderColor={tableBorderColor} w="full" maxW="full" overflow="auto">
        {loadingClients && !clients ? (
          <Stack w="full" p={4}>
            <Skeleton rounded="lg" height="24px" />
            <Skeleton rounded="lg" height="32px" />
            <Skeleton rounded="lg" height="32px" />
            <Skeleton rounded="lg" height="32px" />
          </Stack>
        ) : (
          <Table>
            <Thead borderBottom="1px solid" borderColor={tableBorderColor}>
              <Tr>
                <Th>{t('clients.list.columns.name')}</Th>
                <Th>{t('clients.list.columns.email')}</Th>
                <Th>{t('clients.list.columns.phone')}</Th>
                <Th></Th>
              </Tr>
            </Thead>
            <Tbody>
              {clients && clients.length > 0 ? (
                clients?.map((client) => (
                  <Tr key={`client-${client.id}`}>
                    <Td>
                      <Link href={`/app/clients/${client.id}`}>{client.name}</Link>
                    </Td>
                    <Td>{client.email}</Td>
                    <Td>{client.phone}</Td>
                    <Td>
                      <HStack justify="end" spacing={1}>
                        <IconButton
                          size="sm"
                          colorScheme="primary"
                          variant="ghost"
                          icon={<FaEdit />}
                          aria-label={t('clients.list.edit')}
                          onClick={() => openClientForm(client)}
                        />
                        <IconButton
                          size="sm"
                          colorScheme="red"
                          variant="ghost"
                          icon={<FaTrash />}
                          aria-label={t('clients.list.delete')}
                          disabled={selectedClient?.id === client.id && deleteClientMutation.isLoading}
                          onClick={() => openDeleteConfirm(client)}
                        />
                      </HStack>
                    </Td>
                  </Tr>
                ))
              ) : (
                <Tr>
                  <Td colSpan={99}>
                    <VStack textAlign="center" p={6}>
                      <Heading fontSize="lg">{t('clients.list.noResults')}</Heading>
                      <Text opacity={0.5}>{t('clients.list.empty')}</Text>
                    </VStack>
                  </Td>
                </Tr>
              )}
            </Tbody>
          </Table>
        )}
      </Box>
      <ClientFormModal client={selectedClient} isOpen={formModal.isOpen} onClose={formModal.onClose} />
      <ConfirmModal
        title={t('deleteClientModal.title')}
        description={t('deleteClientModal.description')}
        isDelete
        isOpen={confirmModal.isOpen}
        onClose={(confirmed) => {
          if (confirmed && selectedClient?.id) deleteClientMutation.mutate(selectedClient.id);
          confirmModal.onClose();
        }}
      />
    </>
  );
}

export default ClientsList;
