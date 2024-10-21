import { BreadCrumb } from 'primereact/breadcrumb';
import { MenuItem } from 'primereact/menuitem';
import { ReactElement } from 'react';

interface Props {
  pageTitle?: string;
  breadcrumbItems?: MenuItem[];
  home?: MenuItem;
  pageActions?: ReactElement | null;
}

export default function PageTitle(props: Props) {
  return props.pageTitle ? (
    <h1 className="text-white text-2xl sm:text-3xl font-bold tracking-wide uppercase shadow-sm">{props.pageTitle}</h1>
  ) : props.breadcrumbItems ? (
    <div className="w-full flex flex-col sm:flex-row gap-2 items-center justify-between">
      <BreadCrumb
        model={props.breadcrumbItems}
        home={props.home}
        className="bg-black"
        pt={{
          root: { className: 'border-none p-0' },
          label: { className: 'text-white font-bold text-sm sm:text-base' },
          menu: { className: 'text-white' },
          menuitem: { className: 'hover:text-primary-500 transition-colors duration-200' },
          separator: { className: 'text-primary-500 mx-2 sm:mx-3' },
          action: { className: '' },
        }}
      />
      {props.pageActions}
    </div>
  ) : (
    <p className="text-white text-sm sm:text-base">WRONG Props given to the PageTitle component</p>
  );
}
