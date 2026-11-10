import { useCallback, useMemo, useRef, useState } from 'react';
import type { ReactionSummary, ReactionTarget, WhoReacted } from '@knowii/common';
import { knowiiApiClient, MAX_REACTION_EMOJI_PER_ITEM, reactionEmojiSchema, useAppData } from '@knowii/common';
import { FaFaceSmile, FaPlus } from 'react-icons/fa6';
import { Button, cn, Dialog, Spinner } from '@/Components/ui';
import type { EmojiPickerProps } from './EmojiPicker';
import { EmojiPicker } from './EmojiPicker';

export interface ReactionBarProps {
  target: ReactionTarget;
  /**
   * The summary from the server. Entries without an emoji are ignored.
   */
  initialReactions: { emoji: string; count: number; reacted: boolean }[] | undefined;
  canReact: boolean;
  /**
   * Short name of the item for screen readers ("this post", "the comment by Ada")
   */
  itemLabel: string;
  /**
   * For tests: loads the emoji data of the picker
   */
  loadEmojiData?: EmojiPickerProps['loadData'];
}

const plural = (count: number) => (count === 1 ? '1 reaction' : `${count} reactions`);

function toSummary(input: ReactionBarProps['initialReactions']): ReactionSummary {
  return (input ?? []).flatMap((entry) => {
    const emoji = reactionEmojiSchema.safeParse(entry.emoji);
    return emoji.success ? [{ emoji: emoji.data, count: entry.count, reacted: entry.reacted }] : [];
  });
}

/**
 * The reactions of a post, a comment or a message: one toggle button per emoji in use (aria-pressed), an emoji picker
 * to add any other emoji (#1204), and the list of who reacted. Updates at once, and goes back if the server refuses.
 */
export function ReactionBar({ target, initialReactions, canReact, itemLabel, loadEmojiData }: ReactionBarProps) {
  const { showToast } = useAppData();
  const [reactions, setReactions] = useState<ReactionSummary>(() => toSummary(initialReactions));
  // Live updates (#1180): a new summary from the server (the item loaded again) replaces the shown one
  const [source, setSource] = useState(initialReactions);
  if (source !== initialReactions) {
    setSource(initialReactions);
    setReactions(toSummary(initialReactions));
  }
  const [whoReacted, setWhoReacted] = useState<{ open: boolean; data: WhoReacted | null }>({ open: false, data: null });

  const [pickerOpen, setPickerOpen] = useState(false);
  const pickerTrigger = useRef<HTMLButtonElement>(null);
  const reacted = useMemo(() => new Set(reactions.filter((reaction) => reaction.reacted).map((reaction) => reaction.emoji)), [reactions]);

  const toggle = async (emoji: string) => {
    const previous = reactions;
    const current = reactions.find((reaction) => reaction.emoji === emoji);
    if (!current && reactions.length >= MAX_REACTION_EMOJI_PER_ITEM) {
      showToast({
        severity: 'warn',
        summary: `This item already has ${MAX_REACTION_EMOJI_PER_ITEM} different reactions. Add yours to one of them.`,
      });
      return;
    }
    // A new emoji comes last, like on the server
    const optimistic: ReactionSummary = current
      ? reactions
          .map((reaction) =>
            reaction.emoji === emoji
              ? { ...reaction, reacted: !reaction.reacted, count: reaction.count + (reaction.reacted ? -1 : 1) }
              : reaction,
          )
          .filter((reaction) => reaction.count > 0)
      : [...reactions, { emoji, count: 1, reacted: true }];
    setReactions(optimistic);

    const result = await knowiiApiClient.communities.reactions.toggle(target, emoji);
    if (result.ok) {
      setReactions(result.data);
    } else {
      setReactions(previous);
      showToast({ severity: 'error', summary: result.message });
    }
  };

  const closePicker = useCallback(() => {
    setPickerOpen(false);
    pickerTrigger.current?.focus();
  }, []);

  const pick = (emoji: string) => {
    closePicker();
    void toggle(emoji);
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
        return (
          <button
            key={reaction.emoji}
            type="button"
            aria-pressed={reaction.reacted}
            // Screen readers read the name of the emoji
            aria-label={`${reaction.emoji}: ${plural(reaction.count)}${reaction.reacted ? ', including yours' : ''}`}
            disabled={!canReact}
            onClick={() => toggle(reaction.emoji)}
            className={cn(
              'flex h-7 cursor-pointer items-center gap-1 rounded-full border px-2 text-sm transition-colors',
              'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-default',
              reaction.reacted ? 'border-secondary bg-secondary/15 font-bold' : 'border-border hover:bg-overlay/10',
            )}
          >
            <span aria-hidden="true">{reaction.emoji}</span>
            <span aria-hidden="true">{reaction.count}</span>
          </button>
        );
      })}

      {canReact && (
        <div className="relative">
          <button
            ref={pickerTrigger}
            type="button"
            aria-label={`Add a reaction to ${itemLabel}`}
            aria-haspopup="dialog"
            aria-expanded={pickerOpen}
            onClick={() => (pickerOpen ? closePicker() : setPickerOpen(true))}
            className="flex h-7 cursor-pointer items-center gap-1 rounded-full border border-border px-2 text-muted hover:bg-overlay/10 hover:text-primary focus-visible:outline-2 focus-visible:outline-primary"
          >
            <FaFaceSmile aria-hidden="true" />
            <FaPlus aria-hidden="true" className="size-2.5" />
          </button>
          {pickerOpen && (
            <EmojiPicker onPick={pick} onClose={closePicker} reacted={reacted} {...(loadEmojiData ? { loadData: loadEmojiData } : {})} />
          )}
        </div>
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
              <li key={entry.emoji}>
                <p className="font-bold">
                  <span className="text-lg">{entry.emoji}</span> ({entry.members.length})
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
