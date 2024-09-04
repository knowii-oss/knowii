import { PropsWithChildren } from 'react';

export const PageContentWrapper = (props: PropsWithChildren<{}>) => {
  return (
    <>
      <main className="mt-8 md:mt-12 lg:mt-16 px-8 md:px-0 text-black/80">
        { props.children }
      </main>
      <footer className=""></footer>
    </>
  );
}
