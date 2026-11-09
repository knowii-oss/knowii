import type { PropsWithChildren } from 'react';

export default function PageContentWrapper(props: PropsWithChildren) {
  return <main className="page-content-boundaries mb-16 grow">{props.children}</main>;
}
