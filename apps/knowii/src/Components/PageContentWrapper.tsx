import type { PropsWithChildren } from 'react';

export type PageContentWidth = 'wide' | 'form';

interface Props {
  width?: PageContentWidth | undefined;
}

export default function PageContentWrapper(props: PropsWithChildren<Props>) {
  return (
    <main className="page-content-boundaries mb-16 grow">
      {props.width === 'form' ? <div className="mx-auto w-full max-w-6xl">{props.children}</div> : props.children}
    </main>
  );
}
