import type { MemberTag } from '@knowii/common';
import { FaLock } from 'react-icons/fa6';
import { Badge, cn } from '@/Components/ui';

export interface MemberTagListProps {
  /**
   * The tags of one member that the reader may see, as the server gives them (by name)
   */
  tags: readonly MemberTag[];
  className?: string;
}

/**
 * The member tags of one member (#1389, ADR 0060): a list named "Tags", one `Badge` per tag in its color. A tag that
 * only the managers see (only a manager gets one) has a lock and the hidden words "managers only". Nothing is
 * rendered without a tag.
 */
export function MemberTagList({ tags, className }: MemberTagListProps) {
  if (tags.length === 0) {
    return null;
  }

  return (
    <ul aria-label="Tags" className={cn('flex flex-wrap gap-1.5', className)}>
      {tags.map((tag) => {
        const forManagers = tag.visibility === 'managers';
        return (
          <li key={tag.cuid} className="max-w-full" title={forManagers ? 'Only the managers see this tag' : undefined}>
            <Badge color={tag.color} icon={forManagers ? <FaLock /> : undefined}>
              {tag.name}
              {forManagers && <span className="sr-only"> (managers only)</span>}
            </Badge>
          </li>
        );
      })}
    </ul>
  );
}
