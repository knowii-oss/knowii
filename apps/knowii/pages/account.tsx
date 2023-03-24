import { Box, Flex, Heading, useColorModeValue, useToast, VStack } from '@chakra-ui/react';
import { useSupabaseClient } from '@supabase/auth-helpers-react';
import { GetServerSideProps } from 'next';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { useCallback } from 'react';
import { FaUser } from 'react-icons/fa';
import { EditableText, Password, Subscription } from '@knowii/client-ui';
import Layout from '../components/layout/layout';
import { useUserName } from '@knowii/client';
import { i18nConfig } from '../../../next-i18next.config.mjs';
import { I18N_TRANSLATIONS_ACCOUNT, I18N_TRANSLATIONS_COMMON } from '@knowii/common';

export const getServerSideProps: GetServerSideProps = async (ctx) => {
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const translations = await serverSideTranslations(locale, [I18N_TRANSLATIONS_COMMON, I18N_TRANSLATIONS_ACCOUNT], i18nConfig);

  return {
    props: { ...translations },
  };
};

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface AccountPageProps {}

export function AccountPage(_props: AccountPageProps) {
  const { t } = useTranslation(I18N_TRANSLATIONS_ACCOUNT);
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
    <Layout customMeta={{ title: t('account') }}>
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

export default AccountPage;
