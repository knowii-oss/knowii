import { parseVideoUrl } from './video-url.fn';

describe('parseVideoUrl (#1206)', () => {
  it('reads the YouTube and Vimeo addresses that the server embeds', () => {
    expect(parseVideoUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ')).toEqual({ provider: 'youtube', id: 'dQw4w9WgXcQ' });
    expect(parseVideoUrl(' https://youtu.be/dQw4w9WgXcQ ')).toEqual({ provider: 'youtube', id: 'dQw4w9WgXcQ' });
    expect(parseVideoUrl('https://m.youtube.com/shorts/dQw4w9WgXcQ')).toEqual({ provider: 'youtube', id: 'dQw4w9WgXcQ' });
    expect(parseVideoUrl('https://vimeo.com/76979871')).toEqual({ provider: 'vimeo', id: '76979871' });
    expect(parseVideoUrl('https://player.vimeo.com/video/76979871')).toEqual({ provider: 'vimeo', id: '76979871' });
  });

  it('refuses other hosts, invalid ids and other schemes', () => {
    expect(parseVideoUrl('https://example.com/watch?v=dQw4w9WgXcQ')).toBeNull();
    expect(parseVideoUrl('https://youtu.be/short')).toBeNull();
    expect(parseVideoUrl('https://vimeo.com/channels/staffpicks')).toBeNull();
    expect(parseVideoUrl('javascript:alert(1)')).toBeNull();
    expect(parseVideoUrl('youtube.com/watch?v=dQw4w9WgXcQ')).toBeNull();
    expect(parseVideoUrl('')).toBeNull();
  });
});
