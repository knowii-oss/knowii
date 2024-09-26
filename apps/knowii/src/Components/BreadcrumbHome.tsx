import { Link } from '@inertiajs/react';
import { MenuItem } from 'primereact/menuitem';
import { DASHBOARD_URL } from '@knowii/common';
import { FaHome } from 'react-icons/fa';

export const breadcrumbHome: MenuItem = {
  label: 'Home',
  template: (item) => (
    <Link href={route(DASHBOARD_URL)}>
      {/* TODO move the text color to the home pt attribute of the Breadcrumb component used in pageTitle */}
      <div className="flex items-center gap-2 text-white hover:text-primary-500 transition-colors duration-200">
        <FaHome />
        {item.label}
      </div>
    </Link>
  ),
};
