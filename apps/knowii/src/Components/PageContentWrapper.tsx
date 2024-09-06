import { PropsWithChildren } from 'react';

export default function PageContentWrapper(props: PropsWithChildren) {
  return (
    <main className="mt-8 md:mt-12 lg:mt-16 sm:mx-4 md:mx-8 lg:mx-16 xl:mx-24 min-h-[32rem] xl:min-h-[48rem] px-4">{props.children}</main>
  );
}
