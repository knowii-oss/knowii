import { PropsWithChildren } from 'react';

export default function PageContentWrapper(props: PropsWithChildren) {
  return <main className="page-content-boundaries min-h-[32rem] xl:min-h-[48rem]">{props.children}</main>;
}
