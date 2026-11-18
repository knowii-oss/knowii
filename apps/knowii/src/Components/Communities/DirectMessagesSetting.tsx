import type { Community } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { FaEnvelope } from 'react-icons/fa6';
import { Card, CardBody, CardHeader, SaveStatus, Switch, useInstantSave } from '@/Components/ui';

export interface DirectMessagesSettingProps {
  community: Community;
  onSaved?: (community: Community) => void;
}

/**
 * The owner turns direct messages on or off for the community (#1186). A switch, saved at once, with its status
 * (#1569). Off, nobody starts a conversation or sends a direct message; the members still read their conversations.
 */
export function DirectMessagesSetting({ community, onSaved }: DirectMessagesSettingProps) {
  const setting = useInstantSave(community.direct_messages_enabled !== false);

  const change = async (value: boolean) => {
    const result = await setting.save(value, () => knowiiApiClient.communities.update(community.cuid, { direct_messages_enabled: value }));
    if (result?.ok) {
      onSaved?.(result.data);
    }
  };

  return (
    <Card as="section" aria-labelledby="direct-messages-title">
      <CardHeader title={<span id="direct-messages-title">Direct messages</span>} icon={<FaEnvelope aria-hidden="true" />} />
      <CardBody>
        <Switch
          checked={setting.value}
          busy={setting.busy}
          onChange={(value) => void change(value)}
          label="Members can write private messages to each other"
          description="One-to-one or in groups of up to 10. When this is off, nobody starts a conversation or sends a message; the members still read their conversations."
          status={<SaveStatus state={setting.saveState} />}
        />
      </CardBody>
    </Card>
  );
}
