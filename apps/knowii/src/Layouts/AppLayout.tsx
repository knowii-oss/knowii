import React, { FormEventHandler, useState } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import ApplicationMark from '@/Components/ApplicationMark';
import Dropdown from '@/Components/Dropdown';
import DropdownLink from '@/Components/DropdownLink';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { CHANGE_CURRENT_TEAM_URL, LOGOUT_URL, Team, useTypedPage } from '@knowii/common';
import { useRoute } from 'ziggy-js';
import ApplicationHeader from '@/Components/ApplicationHeader';

interface Props {
  title: string;
  header?: React.ReactNode;
  children: React.ReactNode;
}

export default function AppLayout(props: Props) {
  const page = useTypedPage();
  const route = useRoute();

  const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

  const switchToTeam: FormEventHandler = (e: React.FormEvent, team: Team) => {
    e.preventDefault();
    router.put(
      route(CHANGE_CURRENT_TEAM_URL),
      {
        team_id: team.id,
      },
      {
        preserveState: false,
      },
    );
  };

  const logout: FormEventHandler = (e) => {
    e.preventDefault();
    router.post(route(LOGOUT_URL));
  };

  return (
    <>
      <Head title={props.title} />

      <div className="bg-gray-50 full-page">
        {/* FIXME add banner */}
        {/*<Banner />*/}

        <ApplicationHeader
          showRegisterButton={false}
          showLoginButton={false}
          showDashboardButton={false}
          showLogoutButton={true}>

        </ApplicationHeader>
        {/* Add nav */}

        {props.header && (
          <header className="bg-gray-600">
            <div className="px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">{props.header}</div>
          </header>
        )}

        <main>{props.children}</main>
      </div>
    </>
  );
}
