import { PropsWithChildren } from 'react';

export default function PageContentWrapper(props: PropsWithChildren) {
  return <main className="page-content-boundaries min-h-[36rem] sm:min-h-[42rem] md:min-h-[52rem] lg:min-h-[44rem] xl:min-h-[46rem]">{props.children}</main>;
}
