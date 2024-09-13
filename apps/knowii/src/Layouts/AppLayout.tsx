import { ReactNode, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { DASHBOARD_URL, LOGOUT_URL, USER_PROFILE_URL, useTypedPage } from '@knowii/common';
import { useRoute } from 'ziggy-js';
import { Sidebar } from 'primereact/sidebar';
import { Button } from 'primereact/button';
import { Divider } from 'primereact/divider';
import { Avatar } from 'primereact/avatar';
import { FaBars, FaHome, FaSignOutAlt, FaUser } from 'react-icons/fa';
import { FaXmark } from 'react-icons/fa6';
import PageHeader from '@/Components/PageHeader';
import ApplicationMark from '@/Components/ApplicationMark';
import Footer from '@/Components/Footer';
import PageContentWrapper from '@/Components/PageContentWrapper';
import PageWrapper from '@/Components/PageWrapper';

interface Props {
  title: string;
  pageTitle: string;
  children: ReactNode;
}

export default function AppLayout(props: Props) {
  const page = useTypedPage();
  const route = useRoute();

  const [mainMenuVisible, setMainMenuVisible] = useState(false);
  const [userMenuVisible, setUserMenuVisible] = useState(false);

  return (
    <>
      <Head title={props.title} />

      <PageWrapper>
        <PageHeader
          compact={true}
          addLinkOnLogo={true}
          showRegisterButton={false}
          showLoginButton={false}
          showDashboardButton={false}
          showLogoutButton={false}
        >
          {/* MAIN Menu */}
          <Sidebar
            className="bg-gray-800 min-w-full sm:min-w-[40%] lg:min-w-[30%]"
            visible={mainMenuVisible}
            onHide={() => setMainMenuVisible(false)}
            closeIcon={<FaXmark className="text-primary-500 text-3xl hover:text-5xl" />}
          >
            <div className="menu-content-wrapper">
              <div className="flex flex-col items-center gap-4">
                <ApplicationMark className="w-12" />
                <span className="text-white font-extrabold text-3xl leading-none">MENU</span>
              </div>
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

          {/* USER Menu */}
          <Sidebar
            className="bg-gray-800 min-w-full sm:min-w-[40%] lg:min-w-[30%]"
            visible={userMenuVisible}
            position={'right'}
            onHide={() => setUserMenuVisible(false)}
            closeIcon={<FaXmark className="text-primary-500 text-3xl hover:text-5xl" />}
          >
            <div className="menu-content-wrapper">
              <div className="flex flex-col items-center gap-4">
                <Avatar shape="circle" label="U" className="min-h-24 min-w-24" imageAlt={page.props.auth.user?.name} />
                <span className="text-white font-extrabold text-3xl leading-none">Welcome, {page.props.auth.user?.name}</span>
              </div>
              <Divider className="" />
              <div className="flex flex-col h-full justify-between">
                <ul id="user-menu" className="w-full flex flex-col gap-4">
                  <li className="user-menu-entry">
                    <Link className="flex flex-row gap-4 items-center h-full w-full" href={route(USER_PROFILE_URL)}>
                      <FaUser className="text-primary-500 w-12 h-12" />
                      <span className="font-bold text-primary-500">Edit profile</span>
                    </Link>
                  </li>
                </ul>
                <div className="flex flex-row justify-center md:justify-end">
                  <Link href={route(LOGOUT_URL)} method="post" className="">
                    <Button aria-label={'Log out'} severity="secondary" className="min-w-64 flex flex-row justify-center text-3xl">
                      <FaSignOutAlt />
                    </Button>
                  </Link>
                </div>
              </div>
            </div>
          </Sidebar>
          <div className="min-h-full">
            <Avatar
              className="min-h-12 min-w-12 hover:outline hover:outline-primary-600"
              shape="circle"
              label="U"
              onClick={() => setUserMenuVisible(!userMenuVisible)}
              imageAlt={page.props.auth.user?.name}
            />
          </div>
        </PageHeader>

        <div className="bg-gradient-to-r from-gray-700 via-gray-500 to-gray-700">
          <div className="px-4 py-3 mx-auto max-w-7xl sm:px-6 lg:px-8 flex justify-center">
            <h1 className="text-white text-2xl sm:text-3xl font-bold tracking-wide uppercase shadow-sm">{props.pageTitle}</h1>
          </div>
        </div>

        <PageContentWrapper>{props.children}</PageContentWrapper>

        <Footer />
      </PageWrapper>
    </>
  );
}
