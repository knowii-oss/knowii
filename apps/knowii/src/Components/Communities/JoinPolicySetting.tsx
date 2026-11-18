import { useState } from 'react';
import type { Community, CommunityJoinPolicy } from '@knowii/common';
import { COMMUNITY_OPEN_JOIN_DESCRIPTION, knowiiApiClient } from '@knowii/common';
import { FaDoorOpen } from 'react-icons/fa6';
import { Button, Card, CardBody, CardHeader, Dialog, DialogCloseButton, Radio, SaveStatus, useInstantSave } from '@/Components/ui';

export interface JoinPolicySettingProps {
  community: Community;
  onSaved?: (community: Community) => void;
}

const OPTIONS: Array<{ value: CommunityJoinPolicy; name: string; description: string }> = [
  { value: 'invitation', name: 'By invitation', description: 'People join when a manager invites them.' },
  { value: 'open', name: 'Open', description: COMMUNITY_OPEN_JOIN_DESCRIPTION },
];

/**
 * Who can join the community (#1486): the owner and the admins choose between "By invitation" (the default) and
 * "Open". A person whom a manager removed cannot join again by themselves, even when it is open. Saved at once, with
 * its status (#1569). "Open" lets anyone with an account in, so a question asks first, and nothing is saved before the
 * yes; "By invitation" saves at once.
 */
export function JoinPolicySetting({ community, onSaved }: JoinPolicySettingProps) {
  const setting = useInstantSave<CommunityJoinPolicy>(community.join_policy ?? 'invitation');
  // The question "Open this community to anyone with an account?" is open
  const [asking, setAsking] = useState(false);

  const save = async (value: CommunityJoinPolicy) => {
    const result = await setting.save(value, () => knowiiApiClient.communities.updateJoinPolicy(community.cuid, value));
    if (result?.ok) {
      onSaved?.(result.data);
    }
  };

  const change = (value: CommunityJoinPolicy) => {
    if (setting.busy || value === setting.value) {
      return;
    }
    if (value === 'open') {
      setAsking(true);
      return;
    }
    void save(value);
  };

  const open = () => {
    setAsking(false);
    void save('open');
  };

  return (
    <Card as="section" aria-labelledby="join-policy-title">
      <CardHeader title={<span id="join-policy-title">Who can join</span>} icon={<FaDoorOpen aria-hidden="true" />} />
      <CardBody>
        <fieldset className="flex flex-col gap-4" aria-busy={setting.busy}>
          <legend className="sr-only">Who can join this community</legend>
          {OPTIONS.map((option) => (
            <label key={option.value} className="flex cursor-pointer items-start gap-3">
              <Radio
                name="join_policy"
                value={option.value}
                checked={setting.value === option.value}
                onChange={() => change(option.value)}
                aria-describedby={`join-policy-${option.value}-hint`}
              />
              <span>
                <span className="font-bold">{option.name}</span>
                <span id={`join-policy-${option.value}-hint`} className="block text-sm text-muted">
                  {option.description}
                </span>
              </span>
            </label>
          ))}
        </fieldset>
        <SaveStatus state={setting.saveState} />
      </CardBody>

      {/* Cancel, Escape and a click outside keep "By invitation"; the dialog gives the focus back to the choice */}
      <Dialog
        open={asking}
        onClose={() => setAsking(false)}
        size="sm"
        title="Open this community to anyone with an account?"
        description="Anyone with an account can then join without an invitation. If you change it back later, the people who joined stay members."
        footer={
          <>
            <DialogCloseButton autoFocus>Cancel</DialogCloseButton>
            <Button onClick={open}>Open the community</Button>
          </>
        }
      />
    </Card>
  );
}
