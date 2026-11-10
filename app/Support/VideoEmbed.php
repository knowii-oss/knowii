<?php

namespace App\Support;

/**
 * Videos that Knowii embeds (#1188, ADR 0029): YouTube through youtube-nocookie.com and Vimeo with "do not track".
 * Any other address is refused. The Content Security Policy allows these two player hosts in frame-src, nothing else
 * (#1183). The pages load the player only when the member asks for it (no request to the provider before).
 */
final class VideoEmbed
{
    // WARNING: aligned with the frame-src of config/secure-headers.php
    public const array PLAYER_HOSTS = ['https://www.youtube-nocookie.com', 'https://player.vimeo.com'];

    /**
     * @return array{provider: 'youtube'|'vimeo', id: string, url: string, embed_url: string}|null
     */
    public static function parse(?string $url): ?array
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        $parts = parse_url($url);
        if ($parts === false || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower(preg_replace('/^(www\.|m\.)/', '', $parts['host']) ?? '');
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        $youtubeId = match (true) {
            $host === 'youtu.be' => ltrim($path, '/'),
            in_array($host, ['youtube.com', 'youtube-nocookie.com'], true) && $path === '/watch' => is_string($query['v'] ?? null) ? $query['v'] : '',
            in_array($host, ['youtube.com', 'youtube-nocookie.com'], true) && preg_match('#^/(embed|shorts|live)/([^/]+)#', $path, $m) === 1 => $m[2],
            default => null,
        };
        if ($youtubeId !== null) {
            return preg_match('/^[A-Za-z0-9_-]{11}$/', $youtubeId) === 1
                ? ['provider' => 'youtube', 'id' => $youtubeId, 'url' => $url, 'embed_url' => 'https://www.youtube-nocookie.com/embed/'.$youtubeId]
                : null;
        }

        if ($host === 'vimeo.com' && preg_match('#^/(\d{6,12})/?$#', $path, $m) === 1) {
            return self::vimeo($m[1], $url);
        }
        if ($host === 'player.vimeo.com' && preg_match('#^/video/(\d{6,12})/?$#', $path, $m) === 1) {
            return self::vimeo($m[1], $url);
        }

        return null;
    }

    /**
     * @return array{provider: 'vimeo', id: string, url: string, embed_url: string}
     */
    private static function vimeo(string $id, string $url): array
    {
        return ['provider' => 'vimeo', 'id' => $id, 'url' => $url, 'embed_url' => 'https://player.vimeo.com/video/'.$id.'?dnt=1'];
    }
}
