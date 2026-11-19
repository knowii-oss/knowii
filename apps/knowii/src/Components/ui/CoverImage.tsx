import { useState } from 'react';
import { FaRegImage } from 'react-icons/fa6';
import { cn } from './cn';

export interface CoverImageProps {
  /**
   * The address of the picture. null shows the placeholder.
   */
  src: string | null;
  /**
   * What the picture shows. An empty text marks a decoration: screen readers skip it.
   */
  alt: string;
  /**
   * `lazy` loads the picture when it comes near the screen. Use `eager` for the cover at the top of a page.
   */
  loading?: 'lazy' | 'eager';
  /**
   * Classes of the box, for example its width. The box keeps its 16:9 shape.
   */
  className?: string;
}

/**
 * The cover picture of an item (an event), in a 16:9 box with the rounded corners of a `Card`. The picture fills the
 * box and is cut at its edges. The box has its size before the picture loads, and keeps it without a picture: with no
 * address, or when the picture cannot load, it shows a neutral placeholder without a text, so the page never moves.
 *
 * Not for the picture of a person (`Avatar`), and not for the pictures of member content (`MarkdownContent` and its
 * viewer).
 */
export function CoverImage({ src, alt, loading = 'lazy', className }: CoverImageProps) {
  // The address of the picture that could not load: the placeholder shows in its place, until the address changes
  const [failedSrc, setFailedSrc] = useState<string | null>(null);
  const hasPicture = src !== null && src !== '' && src !== failedSrc;

  return (
    <div
      className={cn(
        'flex aspect-video w-full items-center justify-center overflow-hidden rounded-xl border border-border bg-well',
        className,
      )}
    >
      {hasPicture ? (
        <img src={src} alt={alt} loading={loading} className="size-full object-cover" onError={() => setFailedSrc(src)} />
      ) : (
        <FaRegImage aria-hidden="true" className="size-8 text-muted" />
      )}
    </div>
  );
}
