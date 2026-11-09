import { useState } from 'react';
import type { Space, SpaceGroup } from '@knowii/common';
import { knowiiApiClient, moveItem, SPACE_TYPE_LABELS, useAppData } from '@knowii/common';
import { FaArrowDown, FaArrowUp, FaPen, FaPlus, FaTrash } from 'react-icons/fa6';
import { Button, Card, CardBody, CardHeader, Dialog, EmptyState } from '@/Components/ui';
import { SpaceDialog } from './SpaceDialog';
import { SpaceGroupDialog } from './SpaceGroupDialog';

export interface SpaceTreeEditorProps {
  communityCuid: string;
  spaceGroups: SpaceGroup[];
  /**
   * Called after each change, to reload the tree from the server
   */
  onChanged: () => void;
}

type PendingDeletion = { kind: 'group'; spaceGroup: SpaceGroup } | { kind: 'space'; space: Space };

/**
 * Lets community admins build the navigation: space groups and spaces, created, edited, moved and deleted.
 * Reordering uses buttons (keyboard and screen reader friendly), not drag and drop.
 */
export function SpaceTreeEditor({ communityCuid, spaceGroups, onChanged }: SpaceTreeEditorProps) {
  const { showToast } = useAppData();
  const [groupDialog, setGroupDialog] = useState<{ open: boolean; spaceGroup?: SpaceGroup | undefined }>({ open: false });
  const [spaceDialog, setSpaceDialog] = useState<{ open: boolean; space?: Space | undefined; spaceGroupCuid?: string | undefined }>({
    open: false,
  });
  const [pendingDeletion, setPendingDeletion] = useState<PendingDeletion | null>(null);
  const [busy, setBusy] = useState(false);

  const run = async (operation: () => Promise<{ ok: boolean; message: string }>, success: string) => {
    setBusy(true);
    const result = await operation();
    setBusy(false);
    if (result.ok) {
      showToast({ severity: 'success', summary: success });
      onChanged();
    } else {
      showToast({ severity: 'error', summary: result.message });
    }
    return result.ok;
  };

  const moveGroup = (index: number, direction: -1 | 1) =>
    run(
      () =>
        knowiiApiClient.communities.spaceGroups.reorder(
          communityCuid,
          moveItem(spaceGroups, index, direction).map((group) => group.cuid),
        ),
      'Space groups reordered',
    );

  const moveSpace = (group: SpaceGroup, index: number, direction: -1 | 1) =>
    run(
      () =>
        knowiiApiClient.communities.spaces.reorder(
          communityCuid,
          group.cuid,
          moveItem(group.spaces ?? [], index, direction).map((space) => space.cuid),
        ),
      'Spaces reordered',
    );

  const confirmDeletion = async () => {
    if (!pendingDeletion) {
      return;
    }
    const deleted =
      pendingDeletion.kind === 'group'
        ? await run(
            () => knowiiApiClient.communities.spaceGroups.delete(communityCuid, pendingDeletion.spaceGroup.cuid),
            'Space group deleted',
          )
        : await run(() => knowiiApiClient.communities.spaces.delete(communityCuid, pendingDeletion.space.cuid), 'Space deleted');
    if (deleted) {
      setPendingDeletion(null);
    }
  };

  return (
    <section aria-labelledby="space-tree-title" className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 id="space-tree-title" className="text-xl font-extrabold">
            Spaces
          </h2>
          <p className="text-sm text-muted">Space groups and spaces, in the order of the navigation.</p>
        </div>
        <Button onClick={() => setGroupDialog({ open: true })}>
          <FaPlus aria-hidden="true" />
          Add a space group
        </Button>
      </div>

      {spaceGroups.length === 0 && (
        <EmptyState
          icon="🗂️"
          title="No space groups yet"
          description="Create a space group (for example “Start Here”), then add spaces to it."
        />
      )}

      {spaceGroups.map((group, groupIndex) => {
        const spaces = group.spaces ?? [];
        return (
          <Card key={group.cuid} aria-label={`Space group ${group.name}`}>
            <CardHeader
              title={group.name}
              titleAs="h3"
              actions={
                <div className="flex flex-wrap gap-1">
                  <Button
                    size="sm"
                    variant="ghost"
                    iconOnly
                    aria-label={`Move ${group.name} up`}
                    disabled={busy || groupIndex === 0}
                    onClick={() => moveGroup(groupIndex, -1)}
                  >
                    <FaArrowUp aria-hidden="true" />
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    iconOnly
                    aria-label={`Move ${group.name} down`}
                    disabled={busy || groupIndex === spaceGroups.length - 1}
                    onClick={() => moveGroup(groupIndex, 1)}
                  >
                    <FaArrowDown aria-hidden="true" />
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    iconOnly
                    aria-label={`Rename ${group.name}`}
                    onClick={() => setGroupDialog({ open: true, spaceGroup: group })}
                  >
                    <FaPen aria-hidden="true" />
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    iconOnly
                    aria-label={`Delete ${group.name}`}
                    onClick={() => setPendingDeletion({ kind: 'group', spaceGroup: group })}
                  >
                    <FaTrash aria-hidden="true" />
                  </Button>
                  <Button size="sm" variant="secondary" onClick={() => setSpaceDialog({ open: true, spaceGroupCuid: group.cuid })}>
                    <FaPlus aria-hidden="true" />
                    Add a space
                  </Button>
                </div>
              }
            />
            <CardBody>
              {spaces.length === 0 ? (
                <p className="text-sm text-muted">No spaces in this group yet.</p>
              ) : (
                <ul className="flex flex-col gap-2" aria-label={`Spaces of ${group.name}`}>
                  {spaces.map((space, spaceIndex) => (
                    <li key={space.cuid} className="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-well px-3 py-2">
                      <span aria-hidden="true" className="w-6 text-center">
                        {space.emoji || SPACE_TYPE_LABELS[space.type].emoji}
                      </span>
                      <span className="min-w-0 grow">
                        <span className="font-bold">{space.name}</span>
                        <span className="ml-2 text-xs text-muted">
                          {SPACE_TYPE_LABELS[space.type].name} · {space.visibility === 'public' ? 'Public' : 'Members only'}
                          {space.posting_policy === 'moderators' ? ' · Moderators post' : ''}
                        </span>
                      </span>
                      <span className="flex gap-1">
                        <Button
                          size="sm"
                          variant="ghost"
                          iconOnly
                          aria-label={`Move ${space.name} up`}
                          disabled={busy || spaceIndex === 0}
                          onClick={() => moveSpace(group, spaceIndex, -1)}
                        >
                          <FaArrowUp aria-hidden="true" />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          iconOnly
                          aria-label={`Move ${space.name} down`}
                          disabled={busy || spaceIndex === spaces.length - 1}
                          onClick={() => moveSpace(group, spaceIndex, 1)}
                        >
                          <FaArrowDown aria-hidden="true" />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          iconOnly
                          aria-label={`Edit ${space.name}`}
                          onClick={() => setSpaceDialog({ open: true, space })}
                        >
                          <FaPen aria-hidden="true" />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          iconOnly
                          aria-label={`Delete ${space.name}`}
                          onClick={() => setPendingDeletion({ kind: 'space', space })}
                        >
                          <FaTrash aria-hidden="true" />
                        </Button>
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </CardBody>
          </Card>
        );
      })}

      <SpaceGroupDialog
        open={groupDialog.open}
        communityCuid={communityCuid}
        spaceGroup={groupDialog.spaceGroup}
        onClose={() => setGroupDialog({ open: false })}
        onSaved={onChanged}
      />

      <SpaceDialog
        open={spaceDialog.open}
        communityCuid={communityCuid}
        spaceGroups={spaceGroups}
        space={spaceDialog.space}
        defaultSpaceGroupCuid={spaceDialog.spaceGroupCuid}
        onClose={() => setSpaceDialog({ open: false })}
        onSaved={onChanged}
      />

      <Dialog
        open={pendingDeletion !== null}
        onClose={() => setPendingDeletion(null)}
        size="sm"
        title={
          pendingDeletion?.kind === 'group' ? `Delete ${pendingDeletion.spaceGroup.name}?` : `Delete ${pendingDeletion?.space.name ?? ''}?`
        }
        description={
          pendingDeletion?.kind === 'group'
            ? 'Only an empty space group can be deleted. Move or delete its spaces first.'
            : 'The space and all its content are deleted. This cannot be undone.'
        }
        footer={
          <>
            <Button variant="secondary" onClick={() => setPendingDeletion(null)} autoFocus>
              Cancel
            </Button>
            <Button variant="danger" onClick={confirmDeletion} loading={busy}>
              Delete
            </Button>
          </>
        }
      />
    </section>
  );
}
