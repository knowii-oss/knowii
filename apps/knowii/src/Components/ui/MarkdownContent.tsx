import { useEffect, useRef, useState } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import type { PageVideo } from '@knowii/common';
import { VideoPlayer } from '../Video/VideoPlayer';
import { cn } from './cn';
import type { ViewerImage } from './ImageViewer';
import { ImageViewer } from './ImageViewer';

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
 * The images of the gallery of a link that the server marked (#1207, ADR 0039), and the position of the image of
 * that link. Null when the link is not an image of a gallery.
 */
export function galleryOfLink(link: Element | null): { images: ViewerImage[]; index: number } | null {
  const gallery = link?.closest('.image-gallery');
  if (!link || !gallery) {
    return null;
  }
  const links = Array.from(gallery.querySelectorAll<HTMLAnchorElement>('a.image-gallery-item'));
  const index = links.findIndex((item) => item === link);
  if (index < 0) {
    return null;
  }
  return {
    index,
    images: links.map((item) => ({ src: item.getAttribute('href') ?? '', alt: item.querySelector('img')?.getAttribute('alt') ?? '' })),
  };
}

/**
 * Shows member content (posts, comments, messages) with the typography of the theme. A video link alone on its line
 * becomes the click-to-load video player (#1206): nothing loads from YouTube or Vimeo before the member presses Play.
 * An image of a gallery opens in the viewer (#1207): the server writes each image as a link to its file, the grid
 * is CSS only (styles.css), and this component holds the one viewer of its content.
 */
export function MarkdownContent({ html, className, id }: MarkdownContentProps) {
  const container = useRef<HTMLDivElement>(null);
  const [viewer, setViewer] = useState<{ images: ViewerImage[]; index: number; open: boolean }>({ images: [], index: 0, open: false });

  useEffect(() => {
    const element = container.current;
    if (!element) {
      return;
    }
    // One listener for every gallery of the content. Enter on a link is a click too
    const onClick = (event: MouseEvent) => {
      // A click with a modifier key opens the file in a new tab or a new window: the browser keeps it
      if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
        return;
      }
      const target = event.target;
      const found = galleryOfLink(target instanceof Element ? target.closest('a.image-gallery-item') : null);
      if (found) {
        event.preventDefault();
        setViewer({ ...found, open: true });
      }
    };
    element.addEventListener('click', onClick);
    return () => element.removeEventListener('click', onClick);
  }, []);

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
    <>
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
      <ImageViewer
        open={viewer.open}
        images={viewer.images}
        index={viewer.index}
        onIndexChange={(index) => setViewer((current) => ({ ...current, index }))}
        // The images stay while the viewer fades out
        onClose={() => setViewer((current) => ({ ...current, open: false }))}
      />
    </>
  );
}
