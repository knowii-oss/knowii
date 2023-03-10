import { Box, Flex, Heading, useColorModeValue, useToast, VStack } from '@chakra-ui/react';
import { useSupabaseClient } from '@supabase/auth-helpers-react';
import { GetServerSideProps } from 'next';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { useCallback } from 'react';
import { FaUser } from 'react-icons/fa';
import { EditableText, Password, Subscription } from '@knowii/client-ui';
import Layout from '../components/layout/Layout';
import { useUserName } from '@knowii/client';

export const getServerSideProps: GetServerSideProps = async (ctx) => {
  return {
    props: { ...(await serverSideTranslations(ctx.locale!, ['common', 'account'])) },
  };
};

export default function AccountPage() {
  const { t } = useTranslation('account');
  const supabaseClient = useSupabaseClient();
  const userName = useUserName();
  const toast = useToast();

  const updateUserName = useCallback(
    async (userName: string) => {
      const { error } = await supabaseClient.auth.updateUser({ data: { full_name: userName } });

      if (error)
        return toast({
          status: 'error',
          title: t('updateUserName.errorMessage'),
        });

      return toast({
        status: 'success',
        title: t('updateUserName.successMessage'),
      });
    },
    [toast, t, supabaseClient.auth],
  );

  return (
    <Layout pageTitle={t('account')}>
      <Box textAlign="center" px={8} pt={36} pb={16} mt={-20} bg={useColorModeValue('primary.50', 'gray.900')}>
        <VStack spacing={4}>
          <Flex w={36} h={36} bg="primary.500" rounded="full" fontSize="7xl" align="center" justify="center" color="white">
            <FaUser />
          </Flex>
          <Heading as="h1">{!!userName && <EditableText defaultValue={userName} onSubmit={updateUserName} />}</Heading>
        </VStack>
      </Box>

      <Box maxW="3xl" mx="auto" p={8}>
        <VStack align="stretch" gap={4}>
          <Subscription />
          <Password />
        </VStack>
      </Box>
    </Layout>
  );
}
