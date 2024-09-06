import { PropsWithChildren } from 'react';
import classNames from 'classnames';
import Footer from '@/Components/Footer';

interface Props {
  className?: string;
}

export const PageContentWrapper = (props: PropsWithChildren<Props>) => {
  return (
    <>
      <main className={classNames('mt-8 md:mt-12 lg:mt-16 px-8 md:px-0 text-black/80 min-h-[48rem]', props.className)}>
        {props.children}
      </main>
      <Footer />
    </>
  );
};
