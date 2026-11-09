import { useState } from 'react';
import type { ReactionKind, ReactionSummary, ReactionTarget, WhoReacted } from '@knowii/common';
import { knowiiApiClient, REACTION_KINDS, REACTION_LABELS, reactionKindSchema, useAppData } from '@knowii/common';
import { FaFaceSmile, FaPlus } from 'react-icons/fa6';
import { Button, cn, Dialog, DropdownMenu, DropdownMenuItem, dropdownMenuItemClasses, Spinner } from '@/Components/ui';

export interface ReactionBarProps {
  target: ReactionTarget;
  /**
   * The summary from the server. Unknown kinds are ignored.
   */
  initialReactions: { kind: string; count: number; reacted: boolean }[] | undefined;
  canReact: boolean;
  /**
   * Short name of the item for screen readers ("this post", "the comment by Ada")
   */
  itemLabel: string;
}

const plural = (count: number) => (count === 1 ? '1 reaction' : `${count} reactions`);

function toSummary(input: ReactionBarProps['initialReactions']): ReactionSummary {
  return (input ?? []).flatMap((entry) => {
    const kind = reactionKindSchema.safeParse(entry.kind);
    return kind.success ? [{ kind: kind.data, count: entry.count, reacted: entry.reacted }] : [];
  });
}

/**
 * The reactions of a post or a comment: one toggle button per reaction kind in use (aria-pressed), a menu to add
 * another kind, and the list of who reacted. Updates at once, and goes back if the server refuses.
 */
export function ReactionBar({ target, initialReactions, canReact, itemLabel }: ReactionBarProps) {
  const { showToast } = useAppData();
  const [reactions, setReactions] = useState<ReactionSummary>(() => toSummary(initialReactions));
  const [whoReacted, setWhoReacted] = useState<{ open: boolean; data: WhoReacted | null }>({ open: false, data: null });

  const toggle = async (kind: ReactionKind) => {
    const previous = reactions;
    const current = reactions.find((reaction) => reaction.kind === kind);
    const optimistic: ReactionSummary = current
      ? reactions
          .map((reaction) =>
            reaction.kind === kind
              ? { ...reaction, reacted: !reaction.reacted, count: reaction.count + (reaction.reacted ? -1 : 1) }
              : reaction,
          )
          .filter((reaction) => reaction.count > 0)
      : [...reactions, { kind, count: 1, reacted: true }].sort((a, b) => REACTION_KINDS.indexOf(a.kind) - REACTION_KINDS.indexOf(b.kind));
    setReactions(optimistic);

    const result = await knowiiApiClient.communities.reactions.toggle(target, kind);
    if (result.ok) {
      setReactions(result.data);
    } else {
      setReactions(previous);
      showToast({ severity: 'error', summary: result.message });
    }
  };

  const openWhoReacted = async () => {
    setWhoReacted({ open: true, data: null });
    const result = await knowiiApiClient.communities.reactions.whoReacted(target);
    if (result.ok) {
      setWhoReacted({ open: true, data: result.data });
    } else {
      setWhoReacted({ open: false, data: null });
      showToast({ severity: 'error', summary: result.message });
    }
  };

  const total = reactions.reduce((sum, reaction) => sum + reaction.count, 0);

  if (total === 0 && !canReact) {
    return null;
  }

  return (
    <div className="flex flex-wrap items-center gap-1.5" role="group" aria-label={`Reactions to ${itemLabel}`}>
      {reactions.map((reaction) => {
        const label = REACTION_LABELS[reaction.kind];
        return (
          <button
            key={reaction.kind}
            type="button"
            aria-pressed={reaction.reacted}
            aria-label={`${label.name}: ${plural(reaction.count)}${reaction.reacted ? ', including yours' : ''}`}
            disabled={!canReact}
            onClick={() => toggle(reaction.kind)}
            className={cn(
              'flex h-7 cursor-pointer items-center gap-1 rounded-full border px-2 text-sm transition-colors',
              'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-default',
              reaction.reacted ? 'border-secondary bg-secondary/15 font-bold' : 'border-border hover:bg-overlay/10',
            )}
          >
            <span aria-hidden="true">{label.emoji}</span>
            <span aria-hidden="true">{reaction.count}</span>
          </button>
        );
      })}

      {canReact && (
        <DropdownMenu
          label={`Add a reaction to ${itemLabel}`}
          align="start"
          triggerClassName="h-7 gap-1 border border-border px-2 text-muted hover:bg-overlay/10 hover:text-primary focus-visible:outline-2 focus-visible:outline-primary"
          trigger={
            <>
              <FaFaceSmile aria-hidden="true" />
              <FaPlus aria-hidden="true" className="size-2.5" />
            </>
          }
        >
          {REACTION_KINDS.map((kind) => {
            const reacted = reactions.some((reaction) => reaction.kind === kind && reaction.reacted);
            return (
              <DropdownMenuItem key={kind}>
                <button type="button" className={dropdownMenuItemClasses} onClick={() => toggle(kind)}>
                  <span aria-hidden="true">{REACTION_LABELS[kind].emoji}</span>
                  {REACTION_LABELS[kind].name}
                  {reacted && <span className="ml-auto text-xs text-muted">(remove)</span>}
                </button>
              </DropdownMenuItem>
            );
          })}
        </DropdownMenu>
      )}

      {total > 0 && (
        <Button size="sm" variant="ghost" onClick={openWhoReacted} aria-label={`See who reacted to ${itemLabel}`}>
          {plural(total)}
        </Button>
      )}

      <Dialog open={whoReacted.open} onClose={() => setWhoReacted({ open: false, data: null })} size="sm" title="Reactions">
        {whoReacted.data === null ? (
          <Spinner label="Loading the reactions" />
        ) : (
          <ul className="flex flex-col gap-3">
            {whoReacted.data.map((entry) => (
              <li key={entry.kind}>
                <p className="font-bold">
                  <span aria-hidden="true">{REACTION_LABELS[entry.kind].emoji} </span>
                  {REACTION_LABELS[entry.kind].name} ({entry.members.length})
                </p>
                <p className="text-sm text-muted">{entry.members.map((member) => member.name).join(', ')}</p>
              </li>
            ))}
          </ul>
        )}
      </Dialog>
    </div>
  );
}
