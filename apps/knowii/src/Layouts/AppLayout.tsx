import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { useTypedPage } from '@knowii/common';
import { useRoute } from 'ziggy-js';
import PageHeader from '@/Components/PageHeader';
import { Sidebar } from 'primereact/sidebar';
import { Button } from 'primereact/button';
import { FaBars } from 'react-icons/fa';

interface Props {
  title: string;
  header?: React.ReactNode;
  children: React.ReactNode;
}

export default function AppLayout(props: Props) {
  const page = useTypedPage();
  const route = useRoute();

  const [menuVisible, setMenuVisible] = useState(false);

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
            className="bg-gray-50 min-w-full md:min-w-[30%] lg:min-w-[30%]"
            visible={menuVisible}
            onHide={() => setMenuVisible(false)}
          >
            <h2>Sidebar</h2>
            <p>
              Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut
              enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
            </p>
          </Sidebar>
          <Button className="" onClick={() => setMenuVisible(!menuVisible)}>
            <FaBars />
          </Button>
        </PageHeader>

        {props.header && (
          <div className="bg-gray-600">
            <div className="px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">{props.header}</div>
          </div>
        )}

        {/* Page content */}
        <main>{props.children}</main>
      </div>
    </>
  );
}
