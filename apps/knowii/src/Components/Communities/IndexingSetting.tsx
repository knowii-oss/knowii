import { useState } from 'react';
import type { Community } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { FaMagnifyingGlass } from 'react-icons/fa6';
import { Button, Card, CardBody, CardHeader, Dialog, DialogCloseButton, SaveStatus, Switch, useInstantSave } from '@/Components/ui';

export interface IndexingSettingProps {
  community: Community;
  onSaved?: (community: Community) => void;
}

/**
 * The owner and the admins decide if search engines may index the public spaces of the community (#1487). Off by
 * default. It also needs an instance that allows indexing. A switch with its status (#1569): turning it off saves at
 * once; turning it on widens what search engines see, so a question asks first, and nothing is saved before the yes.
 */
export function IndexingSetting({ community, onSaved }: IndexingSettingProps) {
  const setting = useInstantSave(community.indexing_enabled === true);
  // The question "Let search engines index this community?" is open
  const [asking, setAsking] = useState(false);

  const save = async (value: boolean) => {
    const result = await setting.save(value, () => knowiiApiClient.communities.updateIndexing(community.cuid, value));
    if (result?.ok) {
      onSaved?.(result.data);
    }
  };

  const change = (value: boolean) => {
    if (value) {
      setAsking(true);
      return;
    }
    void save(false);
  };

  const allow = () => {
    setAsking(false);
    void save(true);
  };

  return (
    <Card as="section" aria-labelledby="indexing-title">
      <CardHeader title={<span id="indexing-title">Search engines</span>} icon={<FaMagnifyingGlass aria-hidden="true" />} />
      <CardBody>
        <Switch
          checked={setting.value}
          busy={setting.busy}
          onChange={change}
          label="Search engines may index the public spaces"
          description="Off by default. When this is on, the public spaces that anyone can read, and the public page of the community, may appear in search engines, with the names and pictures of their authors. Members-only spaces and chat spaces are never indexed."
          status={<SaveStatus state={setting.saveState} />}
        />
      </CardBody>

      {/* Cancel, Escape and a click outside leave the switch off; the dialog gives the focus back to the switch */}
      <Dialog
        open={asking}
        onClose={() => setAsking(false)}
        size="sm"
        title="Let search engines index this community?"
        description="Search engines can then show the public page and the public spaces of this community in their results. If you turn it off later, search engines need time to forget the pages."
        footer={
          <>
            <DialogCloseButton autoFocus>Cancel</DialogCloseButton>
            <Button onClick={allow}>Allow indexing</Button>
          </>
        }
      />
    </Card>
  );
}
