import type { PropsWithChildren } from 'react';

export default function PageWrapper(props: PropsWithChildren) {
  return <div className="flex min-h-screen w-full flex-col bg-background text-primary">{props.children}</div>;
}
