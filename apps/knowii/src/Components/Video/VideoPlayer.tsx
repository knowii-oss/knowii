import { useState } from 'react';
import type { PageVideo } from '@knowii/common';
import { safeHttpUrl } from '@knowii/common';
import { FaArrowUpRightFromSquare, FaPlay } from 'react-icons/fa6';

const PROVIDER_NAMES: Record<PageVideo['provider'], string> = { youtube: 'YouTube', vimeo: 'Vimeo' };

export interface VideoPlayerProps {
  video: PageVideo;
  // What the video is about, for screen readers ("Video: Install Obsidian")
  title: string;
}

/**
 * The video of a lesson (#1188, ADR 0029). Nothing loads from the provider until the member presses Play: then the
 * privacy-friendly player (youtube-nocookie.com, Vimeo with "do not track") replaces the button and starts. 16:9, no
 * inline style (CSP).
 */
export function VideoPlayer({ video, title }: VideoPlayerProps) {
  const [playing, setPlaying] = useState(false);
  const provider = PROVIDER_NAMES[video.provider];
  // YouTube starts at once. The Vimeo player refuses autoplay in some browsers ("Unable to play video"): it shows its own
  // Play button instead
  const source = video.provider === 'youtube' ? `${video.embed_url}?autoplay=1` : video.embed_url;
  const original = safeHttpUrl(video.url);

  return (
    <figure className="flex flex-col gap-2">
      <div className="aspect-video w-full overflow-hidden rounded-lg border border-border bg-black">
        {playing ? (
          <iframe
            src={source}
            title={`Video: ${title}`}
            className="size-full"
            allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
            allowFullScreen
            referrerPolicy="strict-origin-when-cross-origin"
          />
        ) : (
          <button
            type="button"
            onClick={() => setPlaying(true)}
            className="group flex size-full flex-col items-center justify-center gap-3 text-white focus-visible:outline-4 focus-visible:-outline-offset-4 focus-visible:outline-primary"
          >
            <span className="flex size-16 items-center justify-center rounded-full bg-secondary transition-transform group-hover:scale-110 motion-reduce:transition-none">
              <FaPlay aria-hidden="true" className="ml-1 size-6" />
            </span>
            <span className="font-bold">Play the video</span>
            <span className="sr-only">: {title}</span>
          </button>
        )}
      </div>
      <figcaption className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
        <span>{playing ? `Played by ${provider}.` : `The ${provider} player loads when you press Play.`}</span>
        {original && (
          <a
            href={original}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-1 underline underline-offset-2 hover:text-primary"
          >
            <FaArrowUpRightFromSquare aria-hidden="true" />
            Watch on {provider}
            <span className="sr-only"> (opens in a new tab)</span>
          </a>
        )}
      </figcaption>
    </figure>
  );
}
