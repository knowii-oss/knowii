import { useEffect, useId, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import type { AssignableCommunityRole, Community, CommunityRole, InvitationAccessGroup, InvitationImport, MemberTag } from '@knowii/common';
import {
  COMMUNITY_ROLE_LABELS,
  COMMUNITY_SETTINGS_URL,
  COMMUNITY_URL,
  grantableRoles,
  INVITATION_IMPORT_TEMPLATE_PATH,
  INVITATION_IMPORT_URL,
  knowiiApiClient,
  useAppData,
} from '@knowii/common';
import { FaFileCsv, FaGear } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import type { BreadcrumbItem } from '@/Components/ui';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  EmptyState,
  InputError,
  InputLabel,
  LoadMoreButton,
  Select,
  Stepper,
  useAccountTimeZone,
  useFocusAfterRender,
} from '@/Components/ui';
import { formatFullDateTime, toDate } from '@knowii/common';
import type { InvitationGrants } from '@/Components/Communities/InvitationGrantsPicker';
import { InvitationGrantsPicker, NO_INVITATION_GRANTS } from '@/Components/Communities/InvitationGrantsPicker';
import { INVITATION_WORDS } from '@/Components/Communities/invitation-words';
import { invitationImportCountsLine } from '@/Components/Communities/invitation-import-text';

export interface InvitationImportPermissions {
  canAddCommunityMembers: boolean;
  canManageAccessGroupMembers: boolean;
  canAssignMemberTags: boolean;
  canDeleteInvitationImports: boolean;
}

export interface InvitationImportPageProps {
  community: Community;
  currentRole: CommunityRole;
  permissions: InvitationImportPermissions;
  accessGroups: InvitationAccessGroup[];
  memberTags: MemberTag[];
  userCuid: string;
}

/**
 * The breadcrumb of both pages: the community, its settings, "Invite from a file"
 */
export function invitationImportBreadcrumb(community: Community, href: string | null): BreadcrumbItem[] {
  return [
    {
      label: community.name,
      href: route(COMMUNITY_URL, { communitySlug: community.slug }),
      icon: <CommunityIcon visibility={community.visibility} />,
    },
    { label: 'Settings', href: route(COMMUNITY_SETTINGS_URL, { communitySlug: community.slug }), icon: <FaGear /> },
    href === null ? { label: INVITATION_WORDS.importTitle } : { label: INVITATION_WORDS.importTitle, href },
  ];
}

const PAGE_SIZE = 20;

/**
 * "Invite from a file" (#1390, ADR 0061), step 1: the form of the upload (a CSV file, the role and the groups and tags of
 * every row), the help with the template, and the earlier imports. The server reads the file: the client only sends it.
 */
export default function InvitationImportsIndexPage(props: InvitationImportPageProps) {
  const { community, permissions } = props;
  const { showToast } = useAppData();
  const zone = useAccountTimeZone();
  const roles = grantableRoles(props.currentRole);
  const fileId = useId();
  const fileErrorId = `${fileId}-error`;
  const helpId = `${fileId}-help`;
  const fileRef = useRef<HTMLInputElement>(null);
  const [role, setRole] = useState<AssignableCommunityRole>('member');
  const [grants, setGrants] = useState<InvitationGrants>(NO_INVITATION_GRANTS);
  const [fileError, setFileError] = useState('');
  const [formError, setFormError] = useState('');
  const [busy, setBusy] = useState(false);

  const [imports, setImports] = useState<InvitationImport[] | null>(null);
  const [nextOffset, setNextOffset] = useState<number | null>(null);
  const [loadingMore, setLoadingMore] = useState(false);
  const [status, setStatus] = useState('');
  const listRef = useRef<HTMLUListElement>(null);
  const focusAfterRender = useFocusAfterRender();

  useEffect(() => {
    let active = true;
    void knowiiApiClient.communities.invitationImports.list(community.cuid, PAGE_SIZE, 0).then((result) => {
      if (!active) {
        return;
      }
      setImports(result.ok ? result.data.items : []);
      setNextOffset(result.ok ? (result.data.pagination.nextOffset ?? null) : null);
    });
    return () => {
      active = false;
    };
  }, [community.cuid]);

  const loadMore = async () => {
    if (nextOffset === null || loadingMore || imports === null) {
      return;
    }
    setLoadingMore(true);
    const result = await knowiiApiClient.communities.invitationImports.list(community.cuid, PAGE_SIZE, nextOffset);
    setLoadingMore(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    const added = result.data.items.filter((item) => !imports.some((shown) => shown.cuid === item.cuid));
    setImports([...imports, ...added]);
    setNextOffset(result.data.pagination.nextOffset ?? null);
    setStatus(INVITATION_WORDS.loaded(added.length));
    const first = added[0];
    if (first) {
      focusAfterRender(() =>
        Array.from(listRef.current?.querySelectorAll<HTMLElement>('a[data-import]') ?? []).find(
          (link) => link.dataset['import'] === first.cuid,
        ),
      );
    }
  };

  const onSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (busy) {
      return;
    }
    const file = fileRef.current?.files?.[0];
    setFormError('');
    if (!file) {
      setFileError(INVITATION_WORDS.chooseFile);
      fileRef.current?.focus();
      return;
    }
    setFileError('');
    setBusy(true);
    const result = await knowiiApiClient.communities.invitationImports.upload(community.cuid, {
      file,
      default_role: role,
      access_groups: grants.accessGroups,
      member_tags: grants.memberTags,
    });
    if (!result.ok) {
      setBusy(false);
      const onFile = result.errors['file']?.[0];
      if (onFile) {
        setFileError(onFile);
        fileRef.current?.focus();
        return;
      }
      const other = Object.values(result.errors)[0]?.[0];
      setFormError(other ?? result.message);
      return;
    }
    router.visit(route(INVITATION_IMPORT_URL, { communitySlug: community.slug, importCuid: result.data.cuid }));
  };

  const roleOptions = roles.map((item) => ({ value: item, label: COMMUNITY_ROLE_LABELS[item] }));

  return (
    <AppLayout
      contentWidth="form"
      browserPageTitle={`${community.name} - ${INVITATION_WORDS.importTitle}`}
      breadcrumbItems={invitationImportBreadcrumb(community, null)}
      breadcrumbHome={breadcrumbHome()}
    >
      <div className="flex flex-col gap-8">
        <h1 className="text-2xl font-bold">{INVITATION_WORDS.importTitle}</h1>
        <Stepper steps={INVITATION_WORDS.importSteps} currentStep={0} />

        <form
          onSubmit={(event) => void onSubmit(event)}
          noValidate
          className="flex flex-col gap-4"
          aria-label={INVITATION_WORDS.importTitle}
        >
          <div>
            <InputLabel htmlFor={fileId}>{INVITATION_WORDS.fileLabel}</InputLabel>
            <input
              ref={fileRef}
              id={fileId}
              type="file"
              accept=".csv,text/csv"
              aria-invalid={fileError !== '' || undefined}
              aria-describedby={fileError !== '' ? `${fileErrorId} ${helpId}` : helpId}
              onChange={() => setFileError('')}
              className="mt-1 block w-full max-w-full text-sm file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-md file:border file:border-border-strong file:bg-well file:px-3 file:font-bold file:text-primary focus-visible:outline-2 focus-visible:outline-primary"
            />
            {fileError !== '' && (
              <p id={fileErrorId} role="alert" className="mt-2 text-sm text-danger-text">
                {fileError}
              </p>
            )}
          </div>
          <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div className="sm:w-44">
              <InputLabel htmlFor={`${fileId}-role`}>{INVITATION_WORDS.roleLabel}</InputLabel>
              <Select<AssignableCommunityRole>
                id={`${fileId}-role`}
                className="mt-1"
                options={roleOptions}
                value={role}
                onChange={setRole}
              />
            </div>
            <InvitationGrantsPicker
              accessGroups={props.accessGroups}
              memberTags={props.memberTags}
              canGiveAccessGroups={permissions.canManageAccessGroupMembers}
              canGiveMemberTags={permissions.canAssignMemberTags}
              value={grants}
              onChange={setGrants}
            />
          </div>
          <InputError message={formError} />
          <div>
            <Button type="submit" loading={busy} leftIcon={<FaFileCsv aria-hidden="true" />}>
              {INVITATION_WORDS.checkFile}
            </Button>
          </div>
          <div id={helpId} className="flex flex-col gap-2 text-sm text-muted">
            <p>{INVITATION_WORDS.importHelp}</p>
            <p>
              <a href={INVITATION_IMPORT_TEMPLATE_PATH} download className="font-bold text-secondary-text underline">
                {INVITATION_WORDS.downloadTemplate}
              </a>
            </p>
          </div>
        </form>

        <Card aria-labelledby="earlier-imports-title">
          <CardHeader title={<span id="earlier-imports-title">{INVITATION_WORDS.earlierImports}</span>} />
          <CardBody className="flex flex-col gap-3">
            {imports !== null && imports.length === 0 ? (
              <EmptyState title={INVITATION_WORDS.noImport} />
            ) : (
              <ul
                ref={listRef}
                aria-labelledby="earlier-imports-title"
                aria-busy={imports === null}
                className="flex flex-col divide-y divide-border"
              >
                {(imports ?? []).map((item) => (
                  <li key={item.cuid} className="py-3">
                    <Link
                      data-import={item.cuid}
                      href={route(INVITATION_IMPORT_URL, { communitySlug: community.slug, importCuid: item.cuid })}
                      className="flex min-h-11 flex-col items-start justify-center text-left rounded-md hover:bg-overlay/5 focus-visible:outline-2 focus-visible:outline-primary"
                    >
                      <span className="font-bold text-primary">{formatFullDateTime(toDate(item.created_at), zone)}</span>
                      <span className="text-sm break-words text-muted">
                        {INVITATION_WORDS.startedBy(item.started_by?.name ?? null)}, {INVITATION_WORDS.states[item.state]},{' '}
                        {invitationImportCountsLine(item)}
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
            {nextOffset !== null && (
              <LoadMoreButton loading={loadingMore} onClick={() => void loadMore()}>
                {INVITATION_WORDS.loadMore}
              </LoadMoreButton>
            )}
            <p role="status" aria-live="polite" className="sr-only">
              {status}
            </p>
          </CardBody>
        </Card>
      </div>
    </AppLayout>
  );
}
