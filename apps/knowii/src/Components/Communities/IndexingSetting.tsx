import { useState } from 'react';
import type { Community } from '@knowii/common';
import { knowiiApiClient, useAppData } from '@knowii/common';
import { FaMagnifyingGlass } from 'react-icons/fa6';
import { Card, CardBody, CardHeader, Checkbox } from '@/Components/ui';

export interface IndexingSettingProps {
  community: Community;
  onSaved?: (community: Community) => void;
}

/**
 * The owner and the admins decide if search engines may index the public spaces of the community (#1487). Off by
 * default. Saved at once. It also needs an instance that allows indexing.
 */
export function IndexingSetting({ community, onSaved }: IndexingSettingProps) {
  const { showToast } = useAppData();
  // The value being saved; null: the value of the community
  const [pending, setPending] = useState<boolean | null>(null);
  const enabled = pending ?? community.indexing_enabled === true;

  const change = async (value: boolean) => {
    setPending(value);
    const result = await knowiiApiClient.communities.updateIndexing(community.cuid, value);
    setPending(null);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    showToast({
      severity: 'success',
      summary: value ? 'Search engines may index the public spaces' : 'Search engines may not index the public spaces',
    });
    onSaved?.(result.data);
  };

  return (
    <Card as="section" aria-labelledby="indexing-title">
      <CardHeader title={<span id="indexing-title">Search engines</span>} icon={<FaMagnifyingGlass aria-hidden="true" />} />
      <CardBody>
        <label className="flex cursor-pointer items-start gap-3">
          <Checkbox
            name="indexing_enabled"
            checked={enabled}
            disabled={pending !== null}
            onChange={(event) => void change(event.target.checked)}
            aria-describedby="indexing-hint"
          />
          <span>
            <span className="font-bold">Search engines may index the public spaces</span>
            <span id="indexing-hint" className="block text-sm text-muted">
              Off by default. When this is on, the public spaces that anyone can read, and the public page of the community, may appear in
              search engines, with the names and pictures of their authors. Members-only spaces and chat spaces are never indexed.
            </span>
          </span>
        </label>
      </CardBody>
    </Card>
  );
}
