<?php

use App\Support\LegalPages;
use Illuminate\Support\Facades\File;

/**
 * The legal pages (#902, #903, #904, #77): one controller, Markdown rendered on the server, placeholders filled from
 * the configuration (ADR 0030).
 */
dataset('legal pages', [
    'privacy policy' => ['/privacy-policy', 'Privacy policy'],
    'terms of service' => ['/terms-of-service', 'Terms of service'],
    'data processing agreement' => ['/dpa', 'Data processing agreement'],
    'imprint' => ['/imprint', 'Imprint'],
    'content policy' => ['/content-policy', 'Content policy'],
    'security' => ['/security', 'Security'],
]);

test('every legal page is public and rendered on the server', function (string $url, string $title) {
    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
        ->component('Legal/Show')
        ->where('title', $title)
        ->where('html', fn (string $html) => str_contains($html, '<h1>'))
    );
})->with('legal pages');

test('no legal page keeps an unknown placeholder', function (string $url) {
    $this->get($url)->assertInertia(fn ($page) => $page->where('html', fn (string $html) => ! str_contains($html, '{{')));
})->with('legal pages');

test('the approved pages are final, a self-hosted instance replaces a page with its own file, a draft says so', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page->where('draft', false));

    $override = storage_path('app/legal/policy.md');
    File::ensureDirectoryExists(dirname($override));
    File::put($override, '---
title: Our privacy
updated: 2027-01-01
draft: true
---

# Our privacy

Example <b>Ltd</b>, {{retention.session}}.');

    try {
        $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
            ->where('title', 'Our privacy')
            ->where('draft', true)
            ->where('updated', '2027-01-01')
            ->where('html', fn (string $html) => str_contains($html, 'Example &lt;b&gt;Ltd&lt;/b&gt;, 2 hours.'))
        );
    } finally {
        File::delete($override);
    }
});

test('the privacy policy and the DPA list every sub-processor of the configuration (#904)', function (string $url) {
    config(['legal.subprocessors' => [
        ['name' => 'Example AI, Inc.', 'country' => 'United States', 'purpose' => 'Summaries', 'safeguard' => 'Standard contractual clauses'],
    ]]);

    $this->get($url)->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<td>Example AI, Inc.</td>') && ! str_contains($html, 'Hetzner Online GmbH</td>'))
    );
})->with(['/privacy-policy', '/dpa']);

test('the configured sub-processors include the services that Knowii calls (#904)', function () {
    $names = implode(' ', array_column(LegalPages::subprocessors(), 'name'));

    expect($names)->toContain('Hetzner')->toContain('Cloudflare')->toContain('Resend')->toContain('EmailOctopus')->toContain('Plausible');
});

test('the privacy policy shows the retention periods of the configuration (ADR 0030)', function () {
    config(['session.lifetime' => 45, 'retention.waitlist_months' => 6]);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'Sessions: 45 minutes after your last activity')
            && str_contains($html, 'at most 6 months after you join it'))
    );
});

test('the imprint names the publisher, the responsible person, the contact and the host (#903)', function () {
    $this->get('/imprint')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'DeveloPassion SRL')
            && str_contains($html, '0717.561.755')
            && str_contains($html, 'BE0717561755')
            && str_contains($html, 'Sébastien Dubois')
            && str_contains($html, 'mailto:contact@developassion.be')
            && str_contains($html, 'Hetzner Online GmbH'))
    );
});

test('the content policy tells how to report illegal content, with the point of contact of the DSA (#902)', function () {
    $this->get('/content-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'How to report content')
            && str_contains($html, 'mailto:contact@developassion.be')
            && str_contains($html, 'Digital Services Act')
            && str_contains($html, 'href="/terms-of-service"'))
    );
});

test('a sub-processor of an optional feature says that only the communities that turn it on use it (#904)', function () {
    config(['legal.subprocessors' => [
        ['name' => 'Example AI, Inc.', 'country' => 'United States', 'purpose' => 'Summaries of posts', 'safeguard' => 'Standard contractual clauses', 'feature' => 'AI summaries'],
    ]]);

    $this->get('/dpa')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<td>Summaries of posts. Only for the communities whose owner turns on AI summaries.</td>')
            && str_contains($html, 'at least 14 days before'))
    );
});

test('the terms of service name DeveloPassion SRL, Belgian law and the public contact address (#1309)', function () {
    $this->get('/terms-of-service')->assertInertia(fn ($page) => $page
        ->where('draft', false)
        ->where('html', fn (string $html) => str_contains($html, 'DeveloPassion SRL')
            && str_contains($html, 'Belgian law applies')
            && str_contains($html, 'goes to the Belgian courts, which have exclusive jurisdiction')
            && str_contains($html, 'mailto:contact@developassion.be')
            && ! str_contains($html, 'knowii.net</a>')
            && ! str_contains($html, '@knowii.net')
            && ! str_contains(strtolower($html), 'arbitration'))
    );
});

test('every legal page shows the same public contact address (#1309)', function (string $url) {
    $this->get($url)->assertInertia(fn ($page) => $page->where('html', fn (string $html) => ! str_contains($html, '@knowii.net')));
})->with('legal pages');

test('every approved legal page is final (#1319)', function (string $url) {
    $this->get($url)->assertInertia(fn ($page) => $page->where('draft', false));
})->with(['/privacy-policy', '/terms-of-service', '/imprint', '/dpa', '/content-policy']);

test('the DPA sends any legal action to the Belgian courts (#1319)', function () {
    $this->get('/dpa')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'Any legal action about this DPA goes to the Belgian courts, which have exclusive jurisdiction')));
});

test('the security page tells how we protect the platform, with the periods of the configuration and how to report a vulnerability (#207)', function () {
    config(['retention.expired_api_tokens_days' => 30]);

    $this->get('/security')->assertInertia(fn ($page) => $page
        ->where('title', 'Security')
        ->where('draft', false)
        ->where('html', fn (string $html) => str_contains($html, 'Germany')
            && str_contains($html, 'valid for 15 minutes and work once')
            && str_contains($html, 'expire after 90 days by default')
            && str_contains($html, 'Report a vulnerability')
            && str_contains($html, 'mailto:contact@developassion.be'))
    );
});

test('the imprint and the privacy policy name a contact person for data protection, not a formal officer (#1355)', function () {
    $this->get('/imprint')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'He is also the contact person for data protection.')));
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'The contact person for data protection is Sébastien Dubois, founder of DeveloPassion SRL.')));
});

test('no legal page names a data protection officer (#1355)', function (string $url) {
    $this->get($url)->assertInertia(fn ($page) => $page->where('html', fn (string $html) => ! str_contains(strtolower($html), 'data protection officer')));
})->with('legal pages');

test('the privacy policy lists the cookies and the browser storage, with the session cookie of the configuration (#1342)', function () {
    config(['session.cookie' => 'example_session', 'session.lifetime' => 120]);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'Knowii uses only the cookies and the browser storage that the service needs, so there is no consent banner.')
            && str_contains($html, '<td><code>example_session</code></td>')
            && str_contains($html, '<td>2 hours after your last activity</td>')
            && str_contains($html, '<td><code>XSRF-TOKEN</code></td>')
            && str_contains($html, '<td><code>remember_web_*</code></td>')
            && str_contains($html, '<td>400 days, or until you sign out</td>')
            && str_contains($html, '<td>Browser storage</td>')
            && ! str_contains($html, 'Knowii uses two cookies'))
    );
});

test('the "Remember me" cookie lasts as long as the privacy policy says (#1342)', function () {
    $guard = auth()->guard('web');
    $minutes = (fn (): int => $this->getRememberDuration())->call($guard);

    expect(intdiv($minutes, 60 * 24))->toBe(400);
});

test('the privacy policy says which data is required, that there is no automated decision, and how to get the safeguards (#1347)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'Your name, email address, username and password are required to create an account. Without them, we cannot give you one. Everything else is optional.')
            && str_contains($html, 'We make no automated decisions about you, and we do no profiling.')
            // The address becomes a link
            && str_contains($html, 'Write to <a href="mailto:contact@developassion.be">contact@developassion.be</a> for a copy of these safeguards.'))
    );
});

test('each sub-processor in the United States names one transfer safeguard (#1347)', function () {
    $american = array_filter(LegalPages::subprocessors(), static fn (array $entry): bool => $entry['country'] === 'United States');

    expect($american)->not->toBeEmpty();
    foreach ($american as $entry) {
        expect($entry['safeguard'])->toBe('EU-US Data Privacy Framework');
    }
});

test('the privacy policy and the DPA list the stored data that the review found missing (#1348)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'your choice to show or hide your online status')
            && str_contains($html, 'what you read in chat spaces and conversations, to show what is new')
            && str_contains($html, 'uploaded images and their alternative text')
            && str_contains($html, 'the API tokens you create and the date of their last use, the token of the \'Remember me\' option, your password reset requests until they expire'))
    );
    $this->get('/dpa')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'read markers') && str_contains($html, 'the alternative text of images')));
});

test('the privacy policy names Turnstile on the sign-up form and describes the audience statistics (#1349)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'The sign-up, waitlist and newsletter forms use Cloudflare Turnstile to block bots.')
            && str_contains($html, 'Audience statistics: page views, counted by Plausible without cookies and without a stored identifier. Our proxy at Cloudflare and Plausible see your IP address to count the view and do not keep it.')
            && str_contains($html, 'To know how many people visit the pages: our legitimate interest in improving the service (art. 6(1)(f)).'))
    );
});

test('the privacy policy says the minimum age of the terms (#1350)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'Knowii is for people of 16 and older. We do not knowingly keep the data of younger people: write to us and we delete the account.')));
    $this->get('/terms-of-service')->assertInertia(fn ($page) => $page->where('html', fn (string $html) => str_contains($html, '16')));
});

test('the instructions of the DPA cover the transfers and the processing that the law requires (#1351)', function () {
    $this->get('/dpa')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'These instructions also cover the transfers of section 8. The Processor can process the data without an instruction when the law of the European Union or of Belgium requires it. In that case, it tells the Controller before the processing, unless that law forbids it.')));
});

test('the DPA lists only the sub-processors of community data, the privacy policy lists them all (#1352)', function () {
    $community = array_column(LegalPages::subprocessors(LegalPages::SCOPE_COMMUNITY), 'name');

    expect($community)->toBe(['Hetzner Online GmbH', 'Cloudflare, Inc.', 'Resend, Inc.']);

    $this->get('/dpa')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<td>Hetzner Online GmbH</td>')
            && str_contains($html, '<td>Cloudflare, Inc.</td>')
            && str_contains($html, '<td>Resend, Inc.</td>')
            && ! str_contains($html, 'EmailOctopus')
            && ! str_contains($html, 'Plausible'))
    );
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<td>EmailOctopus Ltd</td>') && str_contains($html, '<td>Plausible Insights OÜ</td>')
            && str_contains($html, '<td>Hetzner Online GmbH</td>'))
    );
});

test('a sub-processor without a scope is in every list (#1352)', function () {
    config(['legal.subprocessors' => [
        ['name' => 'No Scope GmbH', 'country' => 'Germany', 'purpose' => 'Hosting', 'safeguard' => 'European Union'],
        ['name' => 'Platform Only Ltd', 'country' => 'Ireland', 'purpose' => 'Statistics', 'safeguard' => 'European Union', 'scope' => ['platform']],
    ]]);

    expect(array_column(LegalPages::subprocessors(LegalPages::SCOPE_COMMUNITY), 'name'))->toBe(['No Scope GmbH'])
        ->and(array_column(LegalPages::subprocessors(LegalPages::SCOPE_PLATFORM), 'name'))->toBe(['No Scope GmbH', 'Platform Only Ltd'])
        ->and(array_column(LegalPages::subprocessors(), 'name'))->toBe(['No Scope GmbH', 'Platform Only Ltd']);
});

test('the DPA says that every sub-processor has the same obligations, in its own paragraph (#1352)', function () {
    $this->get('/dpa')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<p>The Processor imposes the same data protection obligations on each sub-processor and stays responsible for them.</p>')
            && str_contains($html, 'so no notice period applies.</p>'))
    );
});

test('the privacy policy tells people who are not members how to use their rights (#1354)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<h2>9. People who are not members</h2>')
            && str_contains($html, 'Content in a community, such as a post or a captured article, can name people who are not members of Knowii. The owner of the community decides about that content.')
            && str_contains($html, 'we pass your request to the owner and remove the content when the law requires it.')
            && str_contains($html, '<h2>10. Changes</h2>'))
    );
});

test('the DPA tells that Knowii records the acceptance date, and how to ask for an export (#1353, approved 2026-11-14)', function () {
    $this->get('/dpa')->assertInertia(fn ($page) => $page->where('html', fn (string $html) => str_contains($html, 'The Controller accepts it when it creates a community. Knowii records the date.')
        && str_contains($html, 'The Controller can ask for an export of its data before the deletion: write to <a href="mailto:contact@developassion.be">contact@developassion.be</a>.')
        && ! str_contains($html, 'through the API')
    ));
});

test('the DPA speaks only about the countries of its sub-processors (#1470, approved 2026-11-15)', function () {
    $this->get('/dpa')->assertInertia(fn ($page) => $page->where('html', fn (string $html) => str_contains($html, 'These transfers use the EU-US Data Privacy Framework or the standard contractual clauses of the European Commission.')
        && ! str_contains($html, 'United Kingdom')
    ));
});

test('the version of a legal page is its updated date (#1353)', function () {
    expect(LegalPages::updated('dpa'))->toMatch('/^\d{4}-\d{2}-\d{2}$/');
});

test('the privacy policy tells about notifications and how long they stay (#1474)', function () {
    config(['retention.notifications_read_days' => 60, 'retention.notifications_unread_days' => 90]);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'and the notifications that tell you when a member replies to you or mentions you (who wrote, about which post, comment or message, and when you saw it).')
            && str_contains($html, 'Notifications: 60 days after you read them, 90 days when you never read them.'))
    );
});

test('the privacy policy says that a public space is read by anyone, with the name and the picture (#1387)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('updated', '2026-11-15')
        ->where('html', fn (string $html) => str_contains($html, 'What you post in a public space of a community can be read by anyone on the internet, with your name and your picture.')
            && ! str_contains($html, 'The space tells you so before you post.')));
});

test('the privacy policy says that a member can delete their content with the account, and write to us afterwards (#1344)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'When you delete your account, you can also delete your posts, comments and messages. Afterwards, write to <a href="mailto:contact@developassion.be">contact@developassion.be</a> to have content removed.'))
    );
});

test('the privacy policy names the identifier of the provider account and the date it was connected (#1385)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'and, when you sign in with a Knowii account, the identifier of that account and the date you connected it.')));
});
