import { Box, Flex, Heading, useColorModeValue, useToast, VStack } from '@chakra-ui/react';
import { useSupabaseClient } from '@supabase/auth-helpers-react';
import { GetServerSideProps } from 'next';
import { useCallback } from 'react';
import { FaUser } from 'react-icons/fa';
import { useTranslations } from 'next-intl';
import { EditableText, Password } from '@knowii/client-ui';
import Layout from '../components/layout/layout';
import { useUserName } from '@knowii/client';
import { i18nConfig } from '../../../i18n.config.mjs';
import { CustomPageProps } from './_app';

export const getServerSideProps: GetServerSideProps<Partial<CustomPageProps>> = async (ctx) => {
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const messages = (await import(`../../../libs/common/src/lib/messages/${locale}.json`)).default;

  return {
    props: { messages },
  };
};

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface AccountPageProps {}

export function AccountPage(_props: AccountPageProps) {
  const t = useTranslations('accountPage');

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
          <Password />
        </VStack>
      </Box>
    </Layout>
  );
}

export default AccountPage;
