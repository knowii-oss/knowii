/**
 * A YouTube or Vimeo video address (#1206). WARNING: the same rules as App\Support\VideoEmbed::parse on the server,
 * which decides what becomes a video: this copy only helps the editor say at once that an address is not a video.
 */
export interface VideoUrl {
  provider: 'youtube' | 'vimeo';
  id: string;
}

const YOUTUBE_ID = /^[A-Za-z0-9_-]{11}$/;

export function parseVideoUrl(input: string): VideoUrl | null {
  let url: URL;
  try {
    url = new URL(input.trim());
  } catch {
    return null;
  }
  if (url.protocol !== 'http:' && url.protocol !== 'https:') {
    return null;
  }

  const host = url.hostname.toLowerCase().replace(/^(www\.|m\.)/, '');
  const path = url.pathname;

  let youtubeId: string | null = null;
  if (host === 'youtu.be') {
    youtubeId = path.replace(/^\//, '');
  } else if (host === 'youtube.com' || host === 'youtube-nocookie.com') {
    if (path === '/watch') {
      youtubeId = url.searchParams.get('v') ?? '';
    } else {
      youtubeId = /^\/(embed|shorts|live)\/([^/]+)/.exec(path)?.[2] ?? null;
    }
  }
  if (youtubeId !== null) {
    return YOUTUBE_ID.test(youtubeId) ? { provider: 'youtube', id: youtubeId } : null;
  }

  const vimeoId =
    (host === 'vimeo.com' && /^\/(\d{6,12})\/?$/.exec(path)?.[1]) ||
    (host === 'player.vimeo.com' && /^\/video\/(\d{6,12})\/?$/.exec(path)?.[1]) ||
    null;
  return vimeoId ? { provider: 'vimeo', id: vimeoId } : null;
}
