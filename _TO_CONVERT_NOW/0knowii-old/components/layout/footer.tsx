import {
  Box,
  Container,
  Heading,
  HStack,
  IconButton,
  Link as StyledLink,
  List,
  ListItem,
  SimpleGrid,
  Text,
  useColorModeValue,
  VStack,
} from '@chakra-ui/react';
import Link from 'next/link';
import { FaEnvelope, FaGithub, FaTwitter } from 'react-icons/fa';
import { useTranslations } from 'next-intl';
import { Logo } from '@knowii/client-ui';
import { PRIVACY_POLICY_URL, TERMS_OF_USE_URL } from '@knowii/common';

// eslint-disable-next-line  @typescript-eslint/no-var-requires
const author = require('../../metadata.json').author;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const contacts = require('../../metadata.json').contacts;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const social = require('../../metadata.json').social;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const sourceCodeUrl = require('../../metadata.json').sourceCodeUrl;

// eslint-disable-next-line
interface FooterProps {}

export function Footer(_props: FooterProps) {
  const t = useTranslations('footer');

  return (
    <Box as="footer" bg={useColorModeValue('gray.50', 'gray.700')} px={4} py={16}>
      <Container maxW="5xl">
        <SimpleGrid spacing={8} columns={[1, 1, 3]}>
          <VStack align="start" spacing={3}>
            <Logo />
            <HStack>
              <IconButton aria-label="Email" as="a" href={`mailto:${contacts.mail}`} variant="ghost" fontSize="xl">
                <FaEnvelope />
              </IconButton>
              <IconButton aria-label="Twitter" as="a" href={social.twitterSebastien} variant="ghost" fontSize="xl">
                <FaTwitter />
              </IconButton>
            </HStack>
            <Text color="gray.400">
              © {author}. {t('allRightsReserved')}
            </Text>
          </VStack>

          <Box>
            <Heading fontSize="base" mb={3}>
              {t('links')}
            </Heading>
            <List spacing={1} opacity={0.75}>
              <ListItem>
                <StyledLink as={Link} href={sourceCodeUrl} className="flex flex-row gap-2 items-center">
                  {t('linkSourceCode')}
                  <FaGithub />
                </StyledLink>
              </ListItem>
              <ListItem>
                <StyledLink as={Link} href={social.newsletterSebastien}>
                  {t('linkNewsletter')}
                </StyledLink>
              </ListItem>
              <ListItem>
                <StyledLink as={Link} href={social.blogSebastien}>
                  {t('linkSebastien')}
                </StyledLink>
              </ListItem>
            </List>
          </Box>

          <Box>
            <Heading fontSize="base" mb={3}>
              {t('legal')}
            </Heading>
            <List spacing={1} opacity={0.75}>
              <ListItem>
                <StyledLink as={Link} href={PRIVACY_POLICY_URL}>
                  {t('privacy')}
                </StyledLink>
              </ListItem>
              <ListItem>
                <StyledLink as={Link} href={TERMS_OF_USE_URL}>
                  {t('terms')}
                </StyledLink>
              </ListItem>
            </List>
          </Box>
        </SimpleGrid>
      </Container>
    </Box>
  );
}

export default Footer;
