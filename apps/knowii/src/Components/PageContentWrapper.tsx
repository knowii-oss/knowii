import { PropsWithChildren } from 'react';
import classNames from 'classnames';

interface Props {
  className?: string;
}

export const PageContentWrapper = (props: PropsWithChildren<Props>) => {
  return (
    <>
      <main className={classNames('mt-8 md:mt-12 lg:mt-16 px-8 md:px-0 text-black/80', props.className)}>{props.children}</main>
      <footer className=""></footer>
    </>
  );
};
