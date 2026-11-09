<?php

/*
 * Crawling and indexing (#104). robots.txt is a request, not a lock: member content is also protected by the
 * policies, and the X-Robots-Tag header keeps authenticated pages and the API out of search engines.
 */
return [
    // false: Disallow: / for every crawler, and noindex on every page (acceptance, staging, private instances).
    // Default: true in production only.
    'allow_indexing' => (bool) env('ROBOTS_ALLOW_INDEXING', env('APP_ENV') === 'production'),

    // Paths that no crawler visits, even when indexing is allowed (member areas, API, documentation)
    'disallow' => [
        '/api/',
        '/dashboard',
        '/user/',
        '/community/',
        '/docs/',
        '/login',
        '/register',
        '/forgot-password',
        '/reset-password/',
        '/two-factor-challenge',
        '/invitations/',
    ],

    // AI crawlers (training and AI search) refused everywhere. Update the list when new ones appear.
    'ai_crawlers' => [
        'GPTBot',
        'ChatGPT-User',
        'OAI-SearchBot',
        'ClaudeBot',
        'Claude-Web',
        'Claude-SearchBot',
        'Claude-User',
        'anthropic-ai',
        'Google-Extended',
        'Applebot-Extended',
        'CCBot',
        'PerplexityBot',
        'Perplexity-User',
        'Bytespider',
        'Amazonbot',
        'meta-externalagent',
        'meta-externalfetcher',
        'FacebookBot',
        'cohere-ai',
        'cohere-training-data-crawler',
        'Diffbot',
        'ImagesiftBot',
        'Omgilibot',
        'Timpibot',
        'YouBot',
        'MistralAI-User',
        'DuckAssistBot',
    ],
];
