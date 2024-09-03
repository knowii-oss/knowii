import React, { useState } from 'react';
import { Link, usePage, router, Head } from '@inertiajs/react';
import ApplicationMark from '@/Components/ApplicationMark';
import Banner from '@/Components/Banner';
import Dropdown from '@/Components/Dropdown';
import DropdownLink from '@/Components/DropdownLink';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';

interface AppLayoutProps {
  title: string;
  header?: React.ReactNode;
  children: React.ReactNode;
}

const AppLayout: React.FC<AppLayoutProps> = ({ title, children, header }) => {
  const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
  const { props }: any = usePage();

  const switchToTeam = (team: any) => {
    router.put(
      route('current-team.update'),
      {
        team_id: team.id,
      },
      {
        preserveState: false,
      },
    );
  };

  const logout = () => {
    router.post(route('logout'));
  };

  return (
    <div>
      <Head title={title} />

      <Banner />

      <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
        <nav className="bg-white border-b border-gray-100 dark:bg-gray-800 dark:border-gray-700">
          <div className="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div className="flex justify-between h-16">
              <div className="flex">
                <div className="flex items-center shrink-0">
                  <Link href={route('dashboard')}>
                    <div className="block w-9 h-9">
                      <ApplicationMark />
                    </div>
                  </Link>
                </div>
                <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                  <NavLink href={route('dashboard')} active={route().current('dashboard')}>
                    Dashboard
                  </NavLink>
                </div>
              </div>

              <div className="hidden sm:flex sm:items-center sm:ms-6">
                {props.jetstream.hasTeamFeatures && (
                  <Dropdown>
                    <Dropdown.Trigger>
                      <span className="inline-flex rounded-md">
                        <button
                          type="button"
                          className="inline-flex items-center px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out bg-white border border-transparent rounded-md dark:text-gray-400 dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700"
                        >
                          {props.auth.user.current_team.name}
                          <svg
                            className="ms-2 -me-0.5 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            strokeWidth="1.5"
                            stroke="currentColor"
                          >
                            <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                          </svg>
                        </button>
                      </span>
                    </Dropdown.Trigger>

                    <Dropdown.Content align="right" width="60">
                      <div className="w-60">
                        <div className="block px-4 py-2 text-xs text-gray-400">Manage Team 3</div>
                        <DropdownLink href={route('teams.show', props.auth.user.current_team)}>Team Settings</DropdownLink>
                        {props.jetstream.canCreateTeams && <DropdownLink href={route('teams.create')}>Create New Team</DropdownLink>}
                        {props.auth.user.all_teams.length > 1 && (
                          <>
                            <div className="border-t border-gray-200 dark:border-gray-600" />
                            <div className="block px-4 py-2 text-xs text-gray-400">Switch Teams</div>
                            {props.auth.user.all_teams.map((team: any) => (
                              <form
                                onSubmit={(e) => {
                                  e.preventDefault();
                                  switchToTeam(team);
                                }}
                                key={team.id}
                              >
                                <DropdownLink as="button">
                                  <div className="flex items-center">
                                    {team.id === props.auth.user.current_team_id && (
                                      <svg
                                        className="w-5 h-5 text-green-400 me-2"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        strokeWidth="1.5"
                                        stroke="currentColor"
                                      >
                                        <path
                                          strokeLinecap="round"
                                          strokeLinejoin="round"
                                          d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                      </svg>
                                    )}
                                    <div>{team.name}</div>
                                  </div>
                                </DropdownLink>
                              </form>
                            ))}
                          </>
                        )}
                      </div>
                    </Dropdown.Content>
                  </Dropdown>
                )}

                <div className="relative ms-3">
                  <Dropdown>
                    <Dropdown.Trigger>
                      {props.jetstream.managesProfilePhotos ? (
                        <button className="flex text-sm transition border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300">
                          <img
                            className="object-cover w-8 h-8 rounded-full"
                            src={props.auth.user.profile_photo_url}
                            alt={props.auth.user.name}
                          />
                        </button>
                      ) : (
                        <span className="inline-flex rounded-md">
                          <button
                            type="button"
                            className="inline-flex items-center px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out bg-white border border-transparent rounded-md dark:text-gray-400 dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700"
                          >
                            {props.auth.user.name}
                            <svg
                              className="ms-2 -me-0.5 h-4 w-4"
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              strokeWidth="1.5"
                              stroke="currentColor"
                            >
                              <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                          </button>
                        </span>
                      )}
                    </Dropdown.Trigger>

                    <Dropdown.Content align="right" width="60">
                      <div className="block px-4 py-2 text-xs text-gray-400">Manage Account</div>
                      <DropdownLink href={route('profile.show')}>Profile</DropdownLink>
                      {props.jetstream.hasApiFeatures && <DropdownLink href={route('api-tokens.index')}>API Tokens</DropdownLink>}
                      <div className="border-t border-gray-200 dark:border-gray-600" />
                      <form
                        onSubmit={(e) => {
                          e.preventDefault();
                          logout();
                        }}
                      >
                        <DropdownLink as="button">Log Out</DropdownLink>
                      </form>
                    </Dropdown.Content>
                  </Dropdown>
                </div>
              </div>

              <div className="flex items-center -me-2 sm:hidden">
                <button
                  className="inline-flex items-center justify-center p-2 text-gray-400 transition duration-150 ease-in-out rounded-md dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400"
                  onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                >
                  <svg className="w-6 h-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path
                      className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M4 6h16M4 12h16M4 18h16"
                    />
                    <path
                      className={showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M6 18L18 6M6 6l12 12"
                    />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <div className={showingNavigationDropdown ? 'block sm:hidden' : 'hidden sm:hidden'}>
            <div className="pt-2 pb-3 space-y-1">
              <ResponsiveNavLink href={route('dashboard')} active={route().current('dashboard')}>
                Dashboard
              </ResponsiveNavLink>
            </div>
            <div className="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
              <div className="flex items-center px-4">
                {props.jetstream.managesProfilePhotos && (
                  <div className="shrink-0 me-3">
                    <img
                      className="object-cover w-10 h-10 rounded-full"
                      src={props.auth.user.profile_photo_url}
                      alt={props.auth.user.name}
                    />
                  </div>
                )}
                <div>
                  <div className="text-base font-medium text-gray-800 dark:text-gray-200">{props.auth.user.name}</div>
                  <div className="text-sm font-medium text-gray-500">{props.auth.user.email}</div>
                </div>
              </div>
              <div className="mt-3 space-y-1">
                <ResponsiveNavLink href={route('profile.show')} active={route().current('profile.show')}>
                  Profile
                </ResponsiveNavLink>
                {props.jetstream.hasApiFeatures && (
                  <ResponsiveNavLink href={route('api-tokens.index')} active={route().current('api-tokens.index')}>
                    API Tokens
                  </ResponsiveNavLink>
                )}
                <form
                  onSubmit={(e) => {
                    e.preventDefault();
                    logout();
                  }}
                >
                  <ResponsiveNavLink href="#" as="button">
                    Log Out
                  </ResponsiveNavLink>
                </form>
                {props.jetstream.hasTeamFeatures && (
                  <>
                    <div className="border-t border-gray-200 dark:border-gray-600" />
                    <div className="block px-4 py-2 text-xs text-gray-400">Manage Team</div>
                    <ResponsiveNavLink href={route('teams.show', props.auth.user.current_team)} active={route().current('teams.show')}>
                      Team Settings
                    </ResponsiveNavLink>
                    {props.jetstream.canCreateTeams && (
                      <ResponsiveNavLink href={route('teams.create')} active={route().current('teams.create')}>
                        Create New Team
                      </ResponsiveNavLink>
                    )}
                    {props.auth.user.all_teams.length > 1 && (
                      <>
                        <div className="border-t border-gray-200 dark:border-gray-600" />
                        <div className="block px-4 py-2 text-xs text-gray-400">Switch Teams</div>
                        {props.auth.user.all_teams.map((team: any) => (
                          <form
                            onSubmit={(e) => {
                              e.preventDefault();
                              switchToTeam(team);
                            }}
                            key={team.id}
                          >
                            <ResponsiveNavLink href="#" as="button">
                              <div className="flex items-center">
                                {team.id !== props.auth.user.current_team_id && (
                                  <svg
                                    className="w-5 h-5 text-green-400 me-2"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    strokeWidth="1.5"
                                    stroke="currentColor"
                                  >
                                    <path
                                      strokeLinecap="round"
                                      strokeLinejoin="round"
                                      d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                  </svg>
                                )}
                                <div>{team.name}</div>
                              </div>
                            </ResponsiveNavLink>
                          </form>
                        ))}
                      </>
                    )}
                  </>
                )}
              </div>
            </div>
          </div>
        </nav>

        {header && (
          <header className="bg-white shadow dark:bg-gray-800">
            <div className="px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">{header}</div>
          </header>
        )}

        <main>{children}</main>
      </div>
    </div>
  );
};

export default AppLayout;
