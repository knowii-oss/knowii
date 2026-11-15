import { useEffect, useMemo, useRef, useState } from 'react';
import type { ApiResult, ThreadFollow } from '@knowii/common';
import { knowiiApiClient, threadFollowLabel } from '@knowii/common';
import { Button } from '@/Components/ui';
import type { ThreadRef } from './notification-thread';

export interface ThreadFollowControlProps {
  communityCuid: string;
  spaceCuid: string;
  // What the page shows: a post, or a first-level chat message
  kind: ThreadRef['kind'];
  cuid: string;
  className?: string;
}

type Action = 'follow' | 'stop' | 'mute';

function call(action: Action | 'get', communityCuid: string, spaceCuid: string, thread: ThreadRef): Promise<ApiResult<ThreadFollow>> {
  const threads = knowiiApiClient.threads;
  if (thread.kind === 'post') {
    const { postCuid } = thread;
    switch (action) {
      case 'get':
        return threads.getPost(communityCuid, spaceCuid, postCuid);
      case 'follow':
        return threads.followPost(communityCuid, spaceCuid, postCuid);
      case 'stop':
        return threads.stopPost(communityCuid, spaceCuid, postCuid);
      case 'mute':
        return threads.mutePost(communityCuid, spaceCuid, postCuid);
    }
  }
  const { messageCuid } = thread;
  switch (action) {
    case 'get':
      return threads.getMessage(communityCuid, spaceCuid, messageCuid);
    case 'follow':
      return threads.followMessage(communityCuid, spaceCuid, messageCuid);
    case 'stop':
      return threads.stopMessage(communityCuid, spaceCuid, messageCuid);
    case 'mute':
      return threads.muteMessage(communityCuid, spaceCuid, messageCuid);
  }
}

/**
 * The state of a thread for the member, as text, with its actions in one small control (#1497, design 2.2, 5.1.6): "Following
 * (you wrote this)", "Following (you replied)", "Following", "Not following" or "Muted", and "Follow", "Stop following",
 * "Mute this thread". For a member of the community only: the caller does not render it for a visitor or an outsider.
 *
 * The state loads from the API when the control mounts (one request for the page, never one per item). The buttons never use `disabled`: `aria-disabled` and a guard. The change is announced after an
 * action only, in a status region; an error is an alert next to the buttons and the focus stays where it was.
 */
export function ThreadFollowControl({ communityCuid, spaceCuid, kind, cuid, className }: ThreadFollowControlProps) {
  const [follow, setFollow] = useState<ThreadFollow | null>(null);
  const [busy, setBusy] = useState(false);
  const [failed, setFailed] = useState(false);
  const [announcement, setAnnouncement] = useState('');
  const busyRef = useRef(false);
  const thread = useMemo<ThreadRef>(() => (kind === 'post' ? { kind, postCuid: cuid } : { kind, messageCuid: cuid }), [kind, cuid]);

  useEffect(() => {
    let active = true;
    void call('get', communityCuid, spaceCuid, thread).then((result) => {
      if (active && result.ok) {
        setFollow(result.data);
      }
    });
    return () => {
      active = false;
    };
  }, [communityCuid, spaceCuid, thread]);

  if (follow === null) {
    return null;
  }

  const run = async (action: Action) => {
    if (busyRef.current) {
      return;
    }
    busyRef.current = true;
    setBusy(true);
    setFailed(false);
    const result = await call(action, communityCuid, spaceCuid, thread);
    busyRef.current = false;
    setBusy(false);
    if (result.ok) {
      setFollow(result.data);
      setAnnouncement(threadFollowLabel(result.data));
    } else {
      setFailed(true);
    }
  };

  const following = follow.state === 'following';

  return (
    <div role="group" aria-label="Notifications for this thread" className={className}>
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <span className="font-bold">{threadFollowLabel(follow)}</span>
        {following ? (
          <Button
            variant="secondary"
            size="sm"
            aria-disabled={busy || undefined}
            aria-busy={busy || undefined}
            onClick={() => void run('stop')}
          >
            Stop following
          </Button>
        ) : (
          <Button
            variant="secondary"
            size="sm"
            aria-disabled={busy || undefined}
            aria-busy={busy || undefined}
            onClick={() => void run('follow')}
          >
            Follow
          </Button>
        )}
        {follow.state !== 'muted' && (
          <Button
            variant="ghost"
            size="sm"
            aria-disabled={busy || undefined}
            aria-busy={busy || undefined}
            onClick={() => void run('mute')}
          >
            Mute this thread
          </Button>
        )}
      </div>
      <p role="status" aria-live="polite" className="sr-only">
        {announcement}
      </p>
      {failed && (
        <p role="alert" className="mt-1 text-sm text-danger-text">
          Not saved. Retry.
        </p>
      )}
    </div>
  );
}
