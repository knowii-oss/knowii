import { useState } from 'react';
import type { Community, CommunityJoinPolicy } from '@knowii/common';
import { COMMUNITY_OPEN_JOIN_DESCRIPTION, knowiiApiClient, useAppData } from '@knowii/common';
import { FaDoorOpen } from 'react-icons/fa6';
import { Card, CardBody, CardHeader, Radio } from '@/Components/ui';

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
 * "Open". Saved at once. A person whom a manager removed cannot join again by themselves, even when it is open.
 */
export function JoinPolicySetting({ community, onSaved }: JoinPolicySettingProps) {
  const { showToast } = useAppData();
  // The value being saved; null: the value of the community
  const [pending, setPending] = useState<CommunityJoinPolicy | null>(null);
  const current = pending ?? community.join_policy ?? 'invitation';

  const change = async (value: CommunityJoinPolicy) => {
    if (pending !== null || value === current) {
      return;
    }
    setPending(value);
    const result = await knowiiApiClient.communities.updateJoinPolicy(community.cuid, value);
    setPending(null);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    showToast({ severity: 'success', summary: value === 'open' ? 'Anyone with an account can join' : 'People join by invitation' });
    onSaved?.(result.data);
  };

  return (
    <Card as="section" aria-labelledby="join-policy-title">
      <CardHeader title={<span id="join-policy-title">Who can join</span>} icon={<FaDoorOpen aria-hidden="true" />} />
      <CardBody>
        <fieldset className="flex flex-col gap-4" aria-busy={pending !== null}>
          <legend className="sr-only">Who can join this community</legend>
          {OPTIONS.map((option) => (
            <label key={option.value} className="flex cursor-pointer items-start gap-3">
              <Radio
                name="join_policy"
                value={option.value}
                checked={current === option.value}
                onChange={() => void change(option.value)}
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
      </CardBody>
    </Card>
  );
}
