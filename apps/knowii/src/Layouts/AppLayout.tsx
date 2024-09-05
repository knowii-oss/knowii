import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { DASHBOARD_URL, useTypedPage } from '@knowii/common';
import { useRoute } from 'ziggy-js';
import PageHeader from '@/Components/PageHeader';
import { Sidebar } from 'primereact/sidebar';
import { Button } from 'primereact/button';
import { FaBars, FaHome } from 'react-icons/fa';
import { FaXmark } from 'react-icons/fa6';
import ApplicationMark from '@/Components/ApplicationMark';
import { Divider } from 'primereact/divider';

interface Props {
  title: string;
  header?: React.ReactNode;
  children: React.ReactNode;
}

export default function AppLayout(props: Props) {
  const page = useTypedPage();
  const route = useRoute();

  const [mainMenuVisible, setMainMenuVisible] = useState(false);

  return (
    <>
      <Head title={props.title} />

      <div className="bg-gray-50 full-page">
        {/* FIXME add banner */}
        {/*<Banner />*/}

        <PageHeader
          compact={true}
          addLinkOnLogo={true}
          showRegisterButton={false}
          showLoginButton={false}
          showDashboardButton={false}
          showLogoutButton={true}
        >
          <Sidebar
            className="bg-gray-800 min-w-full sm:min-w-[40%] lg:min-w-[30%]"
            visible={mainMenuVisible}
            onHide={() => setMainMenuVisible(false)}
            closeIcon={<FaXmark className="text-primary-500 text-3xl hover:text-5xl" />}
          >
            <div className="flex flex-col items-center gap-4">
              <ApplicationMark className="w-12" />
              <span className="text-white font-extrabold text-3xl leading-none">MENU</span>
              <Divider className="" />
              <ul id="main-menu" className="w-full flex flex-col gap-4">
                <li className="main-menu-entry">
                  <Link href={route(DASHBOARD_URL)}>
                    <FaHome />
                    Dashboard
                  </Link>
                </li>
              </ul>
            </div>
          </Sidebar>
          <Button className="" onClick={() => setMainMenuVisible(!mainMenuVisible)}>
            <FaBars />
          </Button>
        </PageHeader>

        {props.header && (
          <div className="bg-gray-600">
            <div className="px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">{props.header}</div>
          </div>
        )}

        {/* Page content */}
        <main className="page-content-wrapper">{props.children}</main>
      </div>
    </>
  );
}
