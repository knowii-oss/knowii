import { useState } from 'react';
import { router } from '@inertiajs/react';
import { FaRightFromBracket } from 'react-icons/fa6';
import { DASHBOARD_URL, knowiiApiClient, useAppData } from '@knowii/common';
import { Button, Dialog } from '@/Components/ui';

interface Props {
  communityCuid: string;
  communityName: string;
  className?: string;
}

/**
 * A member leaves the community by themselves (#1338), after a confirmation. The owner never sees it: they transfer
 * the ownership first, or delete the community.
 */
export default function LeaveCommunityButton({ communityCuid, communityName, className }: Props) {
  const { showToast } = useAppData();
  const [confirming, setConfirming] = useState(false);
  const [busy, setBusy] = useState(false);

  const leave = async () => {
    setBusy(true);
    const result = await knowiiApiClient.communities.members.leave(communityCuid);
    if (!result.ok) {
      setBusy(false);
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    showToast({ severity: 'success', summary: `You left ${communityName}` });
    router.visit(route(DASHBOARD_URL), { preserveState: false });
  };

  return (
    <>
      <Button variant="danger" size="sm" onClick={() => setConfirming(true)} {...(className === undefined ? {} : { className })}>
        <FaRightFromBracket aria-hidden="true" />
        Leave this community
      </Button>

      <Dialog
        open={confirming}
        onClose={() => setConfirming(false)}
        size="sm"
        title={`Leave ${communityName}?`}
        description="You lose access to the community, to its access groups and to your course progress. What you wrote stays. To come back, you need a new invitation."
        footer={
          <>
            <Button variant="secondary" onClick={() => setConfirming(false)} autoFocus>
              Cancel
            </Button>
            <Button variant="danger" onClick={() => void leave()} loading={busy}>
              Leave
            </Button>
          </>
        }
      />
    </>
  );
}
