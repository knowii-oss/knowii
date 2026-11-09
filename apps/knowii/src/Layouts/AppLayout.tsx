import type { ReactElement, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { API_TOKENS_URL, DASHBOARD_URL, LOGOUT_URL, USER_PROFILE_URL, useAppData, useTypedPage } from '@knowii/common';
import { useRoute } from 'ziggy-js';
import { FaBars, FaHome, FaKey, FaSignOutAlt, FaUser } from 'react-icons/fa';
import PageHeader from '@/Components/PageHeader';
import ApplicationMark from '@/Components/ApplicationMark';
import Footer from '@/Components/Footer';
import MainMenu from '@/Components/MainMenu';
import PageContentWrapper, { type PageContentWidth } from '@/Components/PageContentWrapper';
import PageWrapper from '@/Components/PageWrapper';
import PageTitle from '@/Components/PageTitle';
import type { BreadcrumbItem } from '@/Components/ui';
import {
  Avatar,
  Button,
  cn,
  Drawer,
  DropdownMenu,
  DropdownMenuItem,
  dropdownMenuItemClasses,
  ErrorBoundary,
  isCurrentUrl,
} from '@/Components/ui';

// A message after a redirect explains why the member landed here: it stays longer than a usual toast
const FLASH_TOAST_LIFE = 8000;

interface Props {
  browserPageTitle: string;
  pageTitle?: string;
  breadcrumbItems?: BreadcrumbItem[];
  breadcrumbHome?: BreadcrumbItem;
  pageActions?: ReactElement | null;
  /**
   * wide (default): the full page frame, for lists, grids and community pages. form: a narrower centered column, for
   * the settings pages (#1264), so that forms do not stretch over a large screen.
   */
  contentWidth?: PageContentWidth;
  children: ReactNode;
}

export default function AppLayout(props: Props) {
  const page = useTypedPage();
  const route = useRoute();
  const user = page.props.auth.user;

  const [mainMenuVisible, setMainMenuVisible] = useState(false);

  // A message flashed by a redirect of the server (for example the link of an invitation, #1242) shows as a toast
  const { showToast } = useAppData();
  const flashMessage = page.props.jetstream.flash?.banner;
  const flashStyle = page.props.jetstream.flash?.bannerStyle;
  // Once per page: React runs effects twice in development (StrictMode)
  const shownFlash = useRef<string | null>(null);
  useEffect(() => {
    if (flashMessage && shownFlash.current !== flashMessage) {
      shownFlash.current = flashMessage;
      showToast({ severity: flashStyle === 'danger' ? 'error' : 'success', summary: flashMessage, life: FLASH_TOAST_LIFE });
    }
  }, [flashMessage, flashStyle, showToast]);

  return (
    <>
      <Head title={props.browserPageTitle} />

      <PageWrapper>
        <PageHeader
          compact={true}
          addLinkOnLogo={true}
          showRegisterButton={false}
          showLoginButton={false}
          showDashboardButton={false}
          showLogoutButton={false}
        >
          {/* MAIN menu */}
          <Button
            variant="secondary"
            iconOnly
            aria-label="Open the main menu"
            aria-expanded={mainMenuVisible}
            onClick={() => setMainMenuVisible(true)}
          >
            <FaBars aria-hidden="true" />
          </Button>
          <Drawer
            open={mainMenuVisible}
            onClose={() => setMainMenuVisible(false)}
            title={
              <>
                <ApplicationMark className="w-8" />
                Menu
              </>
            }
          >
            <MainMenu
              entries={[{ label: 'Dashboard', href: route(DASHBOARD_URL), icon: <FaHome /> }]}
              currentUrl={page.url}
              onNavigate={() => setMainMenuVisible(false)}
            />
          </Drawer>

          {/* USER menu */}
          <DropdownMenu
            label="Open the user menu"
            trigger={<Avatar name={user?.name} imageUrl={user?.avatar_url} size="md" />}
            triggerClassName="rounded-full"
            header={
              <div className="flex items-center gap-3">
                <Avatar name={user?.name} imageUrl={user?.avatar_url} size="lg" />
                <div className="min-w-0">
                  <p className="truncate font-bold">{user?.name}</p>
                  <p className="truncate text-xs text-muted">{user?.email}</p>
                </div>
              </div>
            }
          >
            <DropdownMenuItem>
              <Link
                href={route(USER_PROFILE_URL)}
                preserveState={true}
                aria-current={isCurrentUrl(route(USER_PROFILE_URL), page.url) ? 'page' : undefined}
                className={cn(dropdownMenuItemClasses, 'aria-[current=page]:bg-overlay/10 aria-[current=page]:font-bold')}
              >
                <FaUser aria-hidden="true" className="text-muted" />
                Edit profile
              </Link>
            </DropdownMenuItem>
            {page.props.jetstream.hasApiFeatures && (
              <DropdownMenuItem>
                <Link
                  href={route(API_TOKENS_URL)}
                  preserveState={true}
                  aria-current={isCurrentUrl(route(API_TOKENS_URL), page.url) ? 'page' : undefined}
                  className={cn(dropdownMenuItemClasses, 'aria-[current=page]:bg-overlay/10 aria-[current=page]:font-bold')}
                >
                  <FaKey aria-hidden="true" className="text-muted" />
                  API tokens
                </Link>
              </DropdownMenuItem>
            )}
            <DropdownMenuItem>
              <Link href={route(LOGOUT_URL)} method="post" as="button" className={dropdownMenuItemClasses}>
                <FaSignOutAlt aria-hidden="true" className="text-muted" />
                Log out
              </Link>
            </DropdownMenuItem>
          </DropdownMenu>
        </PageHeader>

        <div className="border-b border-border bg-surface">
          <div className="page-container flex justify-center py-3">
            <PageTitle
              pageTitle={props.pageTitle}
              breadcrumbItems={props.breadcrumbItems}
              home={props.breadcrumbHome}
              pageActions={props.pageActions}
            />
          </div>
        </div>

        <PageContentWrapper width={props.contentWidth}>
          {/* A failing page keeps the header, the navigation and the footer; a visit to another URL clears the error */}
          <ErrorBoundary resetKey={page.url}>{props.children}</ErrorBoundary>
        </PageContentWrapper>

        <Footer />
      </PageWrapper>
    </>
  );
}
