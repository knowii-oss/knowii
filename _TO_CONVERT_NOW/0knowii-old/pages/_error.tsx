import Error from 'next/error';
import { GetServerSideProps } from 'next';
import { CustomPageProps } from './_app';
import { PageNotFoundError } from 'next/dist/shared/lib/utils';
import InternalServerErrorPage from './500';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface ErrorPageProps {
  statusCode: number;
}

export const getServerSideProps: GetServerSideProps<Partial<CustomPageProps> & ErrorPageProps> = async ({ res }) => {
  const statusCode = res ? res.statusCode : 404;

  return {
    props: {
      statusCode,
      // Note that when `now` is passed to the app, you need to make sure the
      // value is updated from time to time, so relative times are updated. See
      // https://next-intl-docs.vercel.app/docs/usage/configuration#global-now-value
      now: new Date().getTime(),
    },
  };
};

export function ErrorPage(props: ErrorPageProps) {
  if (props.statusCode === 404) {
    return PageNotFoundError;
  } else if (props.statusCode === 500) {
    return InternalServerErrorPage;
  }
  return <Error statusCode={props.statusCode} />;
}

export default ErrorPage;
