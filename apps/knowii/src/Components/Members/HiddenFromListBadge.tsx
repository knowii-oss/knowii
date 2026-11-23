import { FaEyeSlash } from 'react-icons/fa6';
import { Badge } from '@/Components/ui';

/**
 * The words of the marker (#1466): a word changes here, and nowhere else
 */
export const HIDDEN_FROM_LIST_WORDS = {
  marker: 'Hidden from the list',
} as const;

/**
 * The marker of an entry that the other members do not find in the member list (#1466): a member who chose to hide and is
 * not on the team. Only the managers and the member themself get such an entry. The text says it; the icon is a
 * decoration.
 */
export function HiddenFromListBadge() {
  return (
    <Badge variant="neutral" icon={<FaEyeSlash aria-hidden="true" />}>
      {HIDDEN_FROM_LIST_WORDS.marker}
    </Badge>
  );
}
