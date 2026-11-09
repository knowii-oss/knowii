import { DASHBOARD_URL } from '@knowii/common';
import { FaHome } from 'react-icons/fa';
import type { BreadcrumbItem } from '@/Components/ui';

/**
 * First breadcrumb item of the pages below the dashboard
 */
export function breadcrumbHome(): BreadcrumbItem {
  return {
    label: 'Home',
    href: route(DASHBOARD_URL),
    icon: <FaHome />,
  };
}
