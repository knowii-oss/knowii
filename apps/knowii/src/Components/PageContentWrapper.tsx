import { PropsWithChildren } from 'react';

export default function PageContentWrapper(props: PropsWithChildren) {
  return <main className="page-content-wrapper">{props.children}</main>;
}
