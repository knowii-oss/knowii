<?php

/*
 * Crawling and indexing (#104). robots.txt is a request, not a lock: member content is also protected by the
 * policies, and the X-Robots-Tag header keeps authenticated pages and the API out of search engines.
 */
return [
    // false: Disallow: / for every crawler, and noindex on every page (acceptance, staging, private instances).
    // Default: true in production only.
    'allow_indexing' => (bool) env('ROBOTS_ALLOW_INDEXING', env('APP_ENV') === 'production'),

    // Paths that no crawler visits, even when indexing is allowed (member areas, API, documentation). The AI crawlers
    // below are refused everywhere, whatever a community decides
    'disallow' => [
        '/api/',
        '/dashboard',
        '/user/',
        // Never public: the pages of the members of a community. The public spaces and the public community page are not
        // blocked as a whole: each response carries its decision (X-Robots-Tag and meta tag, #1487)
        '/community/*/about',
        '/community/*/settings',
        '/community/*/member/',
        '/community/*/messages',
        '/community-invitations/',
        // The page of an invite link and the address of a link (#1391): never in a search engine. Two lines, so that
        // no other address that starts with /invite is blocked
        '/invite$',
        '/invite/',
        '/docs/',
        '/login',
        // The sign-in with an OpenID Connect provider (#1385): a crawler never starts a flow
        '/auth/',
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
