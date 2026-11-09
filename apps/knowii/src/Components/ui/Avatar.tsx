import { cn } from './cn';

export type AvatarSize = 'sm' | 'md' | 'lg' | 'xl';

const sizeClasses: Record<AvatarSize, string> = {
  sm: 'size-8 text-xs',
  md: 'size-10 text-sm',
  lg: 'size-12 text-base',
  xl: 'size-20 text-2xl',
};

/**
 * Up to two initials: "Ada Lovelace" gives "AL", "ada" gives "A"
 */
export function getInitials(name: string | null | undefined): string {
  const words = (name ?? '').trim().split(/\s+/).filter(Boolean);
  if (words.length === 0) {
    return '?';
  }
  const first = words[0]?.charAt(0) ?? '';
  const last = words.length > 1 ? (words[words.length - 1]?.charAt(0) ?? '') : '';
  return `${first}${last}`.toUpperCase();
}

interface AvatarProps {
  name: string | null | undefined;
  imageUrl?: string | null | undefined;
  size?: AvatarSize;
  className?: string;
}

/**
 * Round picture of a person, with initials when there is no image. Decorative: the name must be given in text nearby
 * or through the accessible name of the parent control.
 */
export function Avatar({ name, imageUrl, size = 'md', className }: AvatarProps) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-secondary font-bold text-white select-none',
        sizeClasses[size],
        className,
      )}
    >
      {imageUrl ? <img src={imageUrl} alt="" className="size-full object-cover" /> : getInitials(name)}
    </span>
  );
}
