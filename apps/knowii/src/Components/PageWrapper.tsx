import { PropsWithChildren } from 'react';

export default function PageWrapper(props: PropsWithChildren) {
  return <main className="bg-gray-50 full-page">{props.children}</main>;
}
