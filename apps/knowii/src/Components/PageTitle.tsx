import type { ReactElement } from 'react';
import type { BreadcrumbItem } from '@/Components/ui';
import { Breadcrumb } from '@/Components/ui';

interface Props {
  pageTitle?: string | undefined;
  breadcrumbItems?: BreadcrumbItem[] | undefined;
  home?: BreadcrumbItem | undefined;
  pageActions?: ReactElement | null | undefined;
}

export default function PageTitle(props: Props) {
  if (props.pageTitle) {
    return <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">{props.pageTitle}</h1>;
  }

  const items = [...(props.home ? [props.home] : []), ...(props.breadcrumbItems ?? [])];

  return (
    <div className="flex w-full flex-col items-center justify-between gap-2 sm:flex-row">
      <Breadcrumb items={items} />
      {props.pageActions}
    </div>
  );
}
