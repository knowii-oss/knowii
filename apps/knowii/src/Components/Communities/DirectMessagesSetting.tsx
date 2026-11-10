import { useState } from 'react';
import type { Community } from '@knowii/common';
import { knowiiApiClient, useAppData } from '@knowii/common';
import { FaEnvelope } from 'react-icons/fa6';
import { Card, CardBody, CardHeader, Checkbox } from '@/Components/ui';

export interface DirectMessagesSettingProps {
  community: Community;
  onSaved?: (community: Community) => void;
}

/**
 * The owner turns direct messages on or off for the community (#1186). Saved at once. Off, nobody starts a
 * conversation or sends a direct message; the members still read their conversations.
 */
export function DirectMessagesSetting({ community, onSaved }: DirectMessagesSettingProps) {
  const { showToast } = useAppData();
  // The value being saved; null: the value of the community
  const [pending, setPending] = useState<boolean | null>(null);
  const enabled = pending ?? community.direct_messages_enabled !== false;

  const change = async (value: boolean) => {
    setPending(value);
    const result = await knowiiApiClient.communities.update(community.cuid, { direct_messages_enabled: value });
    setPending(null);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    showToast({ severity: 'success', summary: value ? 'Direct messages are on' : 'Direct messages are off' });
    onSaved?.(result.data);
  };

  return (
    <Card as="section" aria-labelledby="direct-messages-title">
      <CardHeader title={<span id="direct-messages-title">Direct messages</span>} icon={<FaEnvelope aria-hidden="true" />} />
      <CardBody>
        <label className="flex cursor-pointer items-start gap-3">
          <Checkbox
            name="direct_messages_enabled"
            checked={enabled}
            disabled={pending !== null}
            onChange={(event) => void change(event.target.checked)}
            aria-describedby="direct-messages-hint"
          />
          <span>
            <span className="font-bold">Members can write private messages to each other</span>
            <span id="direct-messages-hint" className="block text-sm text-muted">
              One-to-one or in groups of up to 10. When this is off, nobody starts a conversation or sends a message; the members still read
              their conversations.
            </span>
          </span>
        </label>
      </CardBody>
    </Card>
  );
}
