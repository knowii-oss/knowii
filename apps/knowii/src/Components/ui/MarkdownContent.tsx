import { useEffect, useRef } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import type { PageVideo } from '@knowii/common';
import { VideoPlayer } from '../Video/VideoPlayer';
import { cn } from './cn';

export interface MarkdownContentProps {
  /**
   * HTML rendered by the server from Markdown (App\Support\Markdown): raw HTML from members is escaped there.
   * Never pass HTML from another source.
   */
  html: string;
  className?: string;
  id?: string;
}

const VIDEO_PROVIDERS: Record<string, PageVideo['provider']> = { youtube: 'youtube', vimeo: 'vimeo' };
const PROVIDER_NAMES: Record<PageVideo['provider'], string> = { youtube: 'YouTube', vimeo: 'Vimeo' };

/**
 * The video of a block that the server marked (#1206, ADR 0031), or null when an attribute is missing
 */
export function videoOfBlock(block: HTMLElement): PageVideo | null {
  const provider = VIDEO_PROVIDERS[block.dataset['videoProvider'] ?? ''];
  const id = block.dataset['videoId'];
  const url = block.dataset['videoUrl'];
  const embedUrl = block.dataset['videoEmbedUrl'];
  return provider && id && url && embedUrl ? { provider, id, url, embed_url: embedUrl } : null;
}

/**
 * Shows member content (posts, comments, messages) with the typography of the theme. A video link alone on its line
 * becomes the click-to-load video player (#1206): nothing loads from YouTube or Vimeo before the member presses Play.
 */
export function MarkdownContent({ html, className, id }: MarkdownContentProps) {
  const container = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const element = container.current;
    if (!element) {
      return;
    }
    const roots: Root[] = [];
    element.querySelectorAll<HTMLElement>('div.video-embed').forEach((block) => {
      const video = videoOfBlock(block);
      if (!video) {
        return;
      }
      // The link inside the block is the fallback without JavaScript: the player replaces it
      block.replaceChildren();
      const root = createRoot(block);
      root.render(<VideoPlayer video={video} title={`${PROVIDER_NAMES[video.provider]} video`} />);
      roots.push(root);
    });
    return () => {
      // Unmount after the current render: React refuses to unmount a root while it renders
      const done = [...roots];
      queueMicrotask(() => done.forEach((root) => root.unmount()));
    };
  }, [html]);

  return (
    <div
      ref={container}
      id={id}
      className={cn(
        'prose max-w-none text-primary dark:prose-invert',
        'prose-a:text-secondary-text prose-a:underline-offset-2 prose-code:before:content-none prose-code:after:content-none',
        'prose-img:rounded-lg [&_.video-embed]:my-4 [&_.video-embed]:max-w-2xl',
        className,
      )}
      // Safe: the server renders the Markdown with raw HTML escaped and unsafe links removed (ADR 0013)
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
}
