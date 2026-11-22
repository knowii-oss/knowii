import { useCallback, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import type { InvitationImport, InvitationImportResendSet } from '@knowii/common';
import { formatFullDateTime, INVITATION_IMPORTS_URL, knowiiApiClient, toDate, useAppData, useSocket } from '@knowii/common';
import { FaPaperPlane, FaTrash } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import { Button, Dialog, Stepper, useAccountTimeZone, useFocusAfterRender } from '@/Components/ui';
import { INVITATION_WORDS } from '@/Components/Communities/invitation-words';
import { InvitationImportRowList } from '@/Components/Communities/InvitationImportRowList';
import { invitationImportCountsLine, invitationImportProgress } from '@/Components/Communities/invitation-import-text';
import type { InvitationImportPageProps } from './Index';
import { invitationImportBreadcrumb } from './Index';

interface Props extends InvitationImportPageProps {
  invitationImport: InvitationImport;
}

/**
 * One import of invitations from a file (#1390, ADR 0061). In `preview` (step 2): the summary, the ignored columns, the
 * refused rows first, then the rows to invite, and "Send" (the person who uploaded the file) and "Discard". After the
 * send (step 3): the counts in a polite status that the live update of the import refreshes (the import is loaded again,
 * never every row), the lists, "Send the mails that were not sent", "Invite again the people who did not join" and
 * "Delete this record".
 *
 * The status region is in the page from the first render, so a screen reader hears each change of it. After "Send" and
 * "Send the mails that were not sent", the focus goes to the summary: the button that had it goes away. The lists load
 * again after an action, and after a live update only when the state of the import changed.
 */
export default function InvitationImportShowPage(props: Props) {
  const { community, permissions, userCuid } = props;
  const { showToast } = useAppData();
  const zone = useAccountTimeZone();
  const [invitationImport, setInvitationImport] = useState<InvitationImport>(props.invitationImport);
  const [version, setVersion] = useState(0);
  const [busy, setBusy] = useState<'send' | 'discard' | InvitationImportResendSet | null>(null);
  const [sendError, setSendError] = useState('');
  const [asking, setAsking] = useState<'discard' | 'delete' | null>(null);
  const [now] = useState(() => new Date());
  const summaryRef = useRef<HTMLDivElement>(null);
  const focusAfterRender = useFocusAfterRender();
  const shownState = useRef(props.invitationImport.state);

  // Show a new version of the import. The row lists load again after an action, else only when the state changed
  const show = useCallback((fresh: InvitationImport, reloadRows: boolean) => {
    setInvitationImport(fresh);
    if (reloadRows || shownState.current !== fresh.state) {
      setVersion((count) => count + 1);
    }
    shownState.current = fresh.state;
  }, []);

  const isPreview = invitationImport.state === 'preview';
  const isUploader = invitationImport.started_by?.cuid === userCuid;
  const date = (value: string) => formatFullDateTime(toDate(value), zone);
  const indexHref = route(INVITATION_IMPORTS_URL, { communitySlug: community.slug });

  const reload = useCallback(async () => {
    const result = await knowiiApiClient.communities.invitationImports.get(community.cuid, invitationImport.cuid);
    if (result.ok) {
      // The counts change at each update; the lists load again only when the state changed
      show(result.data, false);
    }
  }, [community.cuid, invitationImport.cuid, show]);

  const userChannel = useMemo(() => ({ type: 'user' as const, userCuid }), [userCuid]);
  useSocket({
    channel: userChannel,
    event: 'invitation_imports.changed',
    callback: (_event, payload) => {
      if (payload.import_cuid === invitationImport.cuid) {
        void reload();
      }
    },
  });

  const send = async () => {
    if (busy !== null) {
      return;
    }
    setBusy('send');
    setSendError('');
    const result = await knowiiApiClient.communities.invitationImports.confirm(community.cuid, invitationImport.cuid);
    setBusy(null);
    if (!result.ok) {
      setSendError(result.message);
      return;
    }
    show(result.data, true);
    focusAfterRender(() => summaryRef.current);
  };

  const resend = async (rows: InvitationImportResendSet) => {
    if (busy !== null) {
      return;
    }
    setBusy(rows);
    setSendError('');
    const result = await knowiiApiClient.communities.invitationImports.resend(community.cuid, invitationImport.cuid, rows);
    setBusy(null);
    if (!result.ok) {
      setSendError(result.message);
      return;
    }
    show(result.data, true);
    focusAfterRender(() => summaryRef.current);
  };

  const remove = async () => {
    setBusy('discard');
    const result = await knowiiApiClient.communities.invitationImports.remove(community.cuid, invitationImport.cuid);
    setBusy(null);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    setAsking(null);
    router.visit(indexHref);
  };

  const { counts } = invitationImport;
  const progress = invitationImportProgress(invitationImport);
  const notSentCount = counts.not_sent;
  const resendFrom = invitationImport.can_resend_not_joined_from;
  const canDiscard = isUploader || permissions.canDeleteInvitationImports;

  return (
    <AppLayout
      contentWidth="form"
      browserPageTitle={`${community.name} - ${INVITATION_WORDS.importTitle}`}
      breadcrumbItems={[...invitationImportBreadcrumb(community, indexHref), { label: date(invitationImport.created_at) }]}
      breadcrumbHome={breadcrumbHome()}
    >
      <div className="flex flex-col gap-8">
        <h1 className="text-2xl font-bold">{INVITATION_WORDS.importTitle}</h1>
        <Stepper steps={INVITATION_WORDS.importSteps} currentStep={isPreview ? 1 : 2} />

        <div className="flex flex-col gap-2">
          <p className="text-sm text-muted">
            {date(invitationImport.created_at)}, {INVITATION_WORDS.startedBy(invitationImport.started_by?.name ?? null)},{' '}
            {INVITATION_WORDS.states[invitationImport.state]}
          </p>
          <div ref={summaryRef} tabIndex={-1} className="rounded-sm focus-visible:outline-2 focus-visible:outline-primary">
            <div role="status" aria-live="polite" className="flex flex-col gap-1">
              <p className="font-bold">
                {isPreview ? INVITATION_WORDS.summary(counts.valid, counts.refused) : invitationImportCountsLine(invitationImport)}
              </p>
              {invitationImport.state === 'sending' && <p>{INVITATION_WORDS.progress(progress.ended, progress.total)}</p>}
            </div>
          </div>
          {invitationImport.ignored_columns.length > 0 && (
            <p className="text-sm break-words">{INVITATION_WORDS.ignoredColumns(invitationImport.ignored_columns)}</p>
          )}
          {invitationImport.state === 'sending' && <p className="text-sm">{INVITATION_WORDS.sendingNote}</p>}
          <p className="text-xs text-muted">
            {isPreview
              ? INVITATION_WORDS.previewKeptUntil(date(invitationImport.kept_until))
              : INVITATION_WORDS.keptUntil(date(invitationImport.kept_until))}
          </p>
        </div>

        {sendError !== '' && (
          <p role="alert" className="rounded-md border border-danger px-3 py-2 text-sm text-danger-text">
            {sendError}
          </p>
        )}

        {isPreview && (
          <div className="flex flex-wrap gap-3">
            {counts.valid > 0 && isUploader && (
              <Button loading={busy === 'send'} leftIcon={<FaPaperPlane aria-hidden="true" />} onClick={() => void send()}>
                {INVITATION_WORDS.send(counts.valid)}
              </Button>
            )}
            {canDiscard && (
              <Button variant="secondary" onClick={() => setAsking('discard')}>
                {INVITATION_WORDS.discard}
              </Button>
            )}
          </div>
        )}
        {isPreview && counts.valid === 0 && <p>{INVITATION_WORDS.noValidRow}</p>}

        {!isPreview && (
          <div className="flex flex-col gap-3">
            <div className="flex flex-wrap gap-3">
              {notSentCount > 0 && (
                <Button
                  className="h-auto max-w-full py-2 whitespace-normal"
                  loading={busy === 'not_sent'}
                  onClick={() => void resend('not_sent')}
                >
                  {INVITATION_WORDS.sendNotSent}
                </Button>
              )}
              {resendFrom !== null && toDate(resendFrom) <= now && counts.sent > 0 && (
                <Button
                  className="h-auto max-w-full py-2 whitespace-normal"
                  variant="secondary"
                  loading={busy === 'not_joined'}
                  onClick={() => void resend('not_joined')}
                >
                  {INVITATION_WORDS.inviteAgain}
                </Button>
              )}
              {permissions.canDeleteInvitationImports && (
                <Button variant="ghost" leftIcon={<FaTrash aria-hidden="true" />} onClick={() => setAsking('delete')}>
                  {INVITATION_WORDS.deleteRecord}
                </Button>
              )}
            </div>
            {resendFrom !== null && toDate(resendFrom) > now && (
              <p className="text-sm">{INVITATION_WORDS.inviteAgainFrom(date(resendFrom))}</p>
            )}
            {invitationImport.resent_at !== null && (
              <p className="text-sm">{INVITATION_WORDS.invitedAgainOn(date(invitationImport.resent_at))}</p>
            )}
          </div>
        )}

        {isPreview ? (
          <>
            <InvitationImportRowList
              communityCuid={community.cuid}
              importCuid={invitationImport.cuid}
              title={INVITATION_WORDS.refusedRows}
              outcomes={['refused']}
              refreshKey={version}
            />
            <InvitationImportRowList
              communityCuid={community.cuid}
              importCuid={invitationImport.cuid}
              title={INVITATION_WORDS.rowsToInvite}
              outcomes={['valid']}
              refreshKey={version}
            />
          </>
        ) : (
          <>
            <InvitationImportRowList
              communityCuid={community.cuid}
              importCuid={invitationImport.cuid}
              title={INVITATION_WORDS.notSent}
              outcomes={['queued', 'held', 'failed', 'skipped']}
              refreshKey={version}
            />
            <InvitationImportRowList
              communityCuid={community.cuid}
              importCuid={invitationImport.cuid}
              title={INVITATION_WORDS.refused}
              outcomes={['refused']}
              refreshKey={version}
            />
            <InvitationImportRowList
              communityCuid={community.cuid}
              importCuid={invitationImport.cuid}
              title={INVITATION_WORDS.sent}
              outcomes={['sent']}
              refreshKey={version}
            />
          </>
        )}
      </div>

      <Dialog
        open={asking !== null}
        onClose={() => setAsking(null)}
        size="sm"
        title={asking === 'delete' ? INVITATION_WORDS.deleteQuestion : INVITATION_WORDS.discardQuestion}
        footer={
          <>
            <Button variant="secondary" onClick={() => setAsking(null)} autoFocus>
              {INVITATION_WORDS.cancel}
            </Button>
            <Button variant="danger" loading={busy === 'discard'} onClick={() => void remove()}>
              {asking === 'delete' ? INVITATION_WORDS.delete : INVITATION_WORDS.discard}
            </Button>
          </>
        }
      />
    </AppLayout>
  );
}
