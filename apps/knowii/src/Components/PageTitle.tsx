import { BreadCrumb } from 'primereact/breadcrumb';
import { MenuItem } from 'primereact/menuitem';

interface Props {
  pageTitle?: string;
  breadcrumbItems?: MenuItem[];
  home?: MenuItem;
}

export default function PageTitle(props: Props) {
  return props.pageTitle ? (
    <h1 className="text-white text-2xl sm:text-3xl font-bold tracking-wide uppercase shadow-sm">{props.pageTitle}</h1>
  ) : props.breadcrumbItems ? (
    <div className="w-full">
      <BreadCrumb
        model={props.breadcrumbItems}
        home={props.home}
        className="w-full bg-gradient-to-r from-gray-700 via-gray-500 to-gray-700"
        pt={{
          root: { className: 'border-none p-0' },
          label: { className: 'text-white font-bold text-sm sm:text-base' },
          menu: { className: 'text-white' },
          menuitem: { className: 'hover:text-primary-500 transition-colors duration-200' },
          separator: { className: 'text-primary-500 mx-2 sm:mx-3' },
          action: { className: '' },
        }}
      />
    </div>
  ) : (
    <p className="text-white text-sm sm:text-base">WRONG Props given to the PageTitle component</p>
  );
}
