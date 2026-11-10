import { useState } from 'react';
import type { CommunityResource, ResourceLevel, ResourceType } from '@knowii/common';
import { knowiiApiClient, resourceLevelSchemaOptions, resourceTypeSchemaOptions, useAppData } from '@knowii/common';
import InputLabel from '@/Components/InputLabel';
import { Button, Select } from '@/Components/ui';

const typeOptions = resourceTypeSchemaOptions.map((option) => ({ value: option.type, label: option.name }));
const levelOptions = resourceLevelSchemaOptions.map((option) => ({ value: option.level, label: option.name, icon: option.icon }));

interface Props {
  communityCuid: string;
  spaceCuid: string;
  resource: CommunityResource;
  onSaved: () => void;
}

/**
 * Change the type and the level of a resource (#1246), for the curator and the members who manage the spaces. A type
 * chosen here stays when the page is captured again; only articles keep captured content (ADR 0018).
 */
export function ResourceChangesForm({ communityCuid, spaceCuid, resource, onSaved }: Props) {
  const { showToast } = useAppData();
  const [type, setType] = useState<ResourceType>(resource.type);
  const [level, setLevel] = useState<ResourceLevel>(resource.level);
  const [saving, setSaving] = useState(false);
  const changed = type !== resource.type || level !== resource.level;

  const save = async (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);
    const result = await knowiiApiClient.resources.update(communityCuid, spaceCuid, resource.cuid, {
      ...(type !== resource.type ? { type } : {}),
      ...(level !== resource.level ? { level } : {}),
    });
    setSaving(false);
    if (result.ok) {
      showToast({ severity: 'success', summary: 'Resource saved' });
      onSaved();
    } else {
      showToast({ severity: 'error', summary: result.message });
    }
  };

  return (
    <form
      onSubmit={save}
      aria-labelledby="resource-changes-title"
      className="mt-6 flex flex-col gap-3 rounded-lg border border-border bg-well p-4"
    >
      <h2 id="resource-changes-title" className="text-sm font-bold">
        Type and level
      </h2>
      <div className="grid gap-3 @md:grid-cols-2">
        <div>
          <InputLabel htmlFor="resource-change-type">Type</InputLabel>
          <Select<ResourceType> id="resource-change-type" className="mt-1" options={typeOptions} value={type} onChange={setType} />
          {!resource.type_chosen && resource.capture_status === 'captured' && (
            <p className="mt-1 text-xs text-muted">Detected from the page.</p>
          )}
        </div>
        <div>
          <InputLabel htmlFor="resource-change-level">Level</InputLabel>
          <Select<ResourceLevel> id="resource-change-level" className="mt-1" options={levelOptions} value={level} onChange={setLevel} />
        </div>
      </div>
      {type !== 'article' && resource.type === 'article' && resource.capture && (
        <p className="text-xs text-muted">Only articles keep the captured text: saving another type removes it.</p>
      )}
      <div>
        <Button type="submit" size="sm" disabled={!changed} loading={saving}>
          Save
        </Button>
      </div>
    </form>
  );
}
