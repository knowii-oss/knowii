<?php

use App\Support\LegalPages;
use Illuminate\Support\Facades\File;

/**
 * The legal texts of the hosted instance (#1653, ADR 0055): the files of deploy/, which its deploy copies to
 * storage/app/legal. The code holds no legal text, so these tests point `legal.path` at that folder and check the words
 * that the owner approved (#902, #903, #904, #77 and the issues that each test names). The neutral behaviour of the legal
 * pages is in LegalPagesTest, on the fixtures of tests/Fixtures/legal.
 *
 * The guard of the neutral names (tests/Unit/InstanceNeutralNamesTest.php) skips this file, and only this one: it names
 * the operator of the hosted instance on purpose. A fork that deletes the folder skips these tests.
 */
function hostedLegalFolder(): string
{
    return base_path('deploy/knowii.net/legal');
}

beforeEach(function () {
    // No skip when the folder is missing: a renamed folder must fail the build, not turn the word checks off in
    // silence. A repository without the texts of this hosted instance deletes this file with the folder.
    expect(is_dir(hostedLegalFolder()))->toBeTrue();

    config(['legal.path' => hostedLegalFolder()]);
});

dataset('hosted legal pages', [
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
})->with('hosted legal pages');

test('no legal page keeps an unknown placeholder', function (string $url) {
    $this->get($url)->assertInertia(fn ($page) => $page->where('html', fn (string $html) => ! str_contains($html, '{{')));
})->with('hosted legal pages');

test('the sub-processors of the hosted instance include the services that it calls (#904)', function () {
    $names = implode(' ', array_column(LegalPages::subprocessors(), 'name'));

    expect($names)->toContain('Hetzner')->toContain('Cloudflare')->toContain('Resend')->toContain('EmailOctopus')->toContain('Plausible');
});

test('the privacy policy shows the retention periods of the configuration (ADR 0030)', function () {
    config(['session.lifetime' => 45, 'retention.waitlist_months' => 6]);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'Sessions: 45 minutes after your last activity')
            && str_contains($html, 'Waitlist: it takes no new address. The addresses given before are deleted after the launch invitation, at most 6 months after you joined it.'))
    );
});

test('the privacy policy names the new email address that waits for its confirmation, with the period of the configuration (#1706)', function () {
    $sentence = 'the date you verified your email address, and a new email address that you asked for, with the date you asked, until you confirm it or cancel it, or for %s at most';

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, sprintf($sentence, '7 days'))));

    config(['retention.pending_email_days' => 3]);
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, sprintf($sentence, '3 days'))));
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
    // The hosted agreement, with a list that names an optional feature
    $this->useLegalFolder([
        'dpa.md' => File::get(hostedLegalFolder().'/dpa.md'),
        'subprocessors.json' => (string) json_encode([
            ['name' => 'Example AI, Inc.', 'country' => 'United States', 'purpose' => 'Summaries of posts', 'safeguard' => 'Standard contractual clauses', 'feature' => 'AI summaries'],
        ]),
    ]);

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
})->with('hosted legal pages');

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
})->with('hosted legal pages');

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

test('the privacy policy names the text that was not sent, kept in the browser (#1622)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('updated', '2026-11-20')
        ->where('html', fn (string $html) => str_contains($html, '<td>The text of a post, a page or a lesson that you wrote and did not send</td>')
            && str_contains($html, '<td>Gives your text back after a closed tab, a reload or a crash. It stays only in your browser and is never sent to the server before you publish it</td>')
            && str_contains($html, '<td>Until you publish or discard it, until you sign out, or 7 days after your last change</td>'))
    );
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
        ->where('html', fn (string $html) => str_contains($html, 'The sign-up and newsletter forms use Cloudflare Turnstile to block bots.')
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
        ->where('html', fn (string $html) => str_contains($html, 'and the notifications that tell you when a member replies to you or mentions you, or when something new happens in a thread that you follow, in a space where you chose Everything, or in a course that you started (who wrote, about which post, comment, message or lesson, and when you saw it)')
            && str_contains($html, 'Notifications: 60 days after you read them, 90 days when you never read them.'))
    );
});

test('the privacy policy tells about the follows, the space levels and the entitlements of a sign-in account (#1505, approved 2026-11-16)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'the threads that you follow or muted and the level that you chose for each space (Everything, Only what is for me, or Muted), which Knowii keeps while you are a member of the community and which you can change at any time,')
            && str_contains($html, 'and, when you sign in with the sign-in provider of this site, the list of what your account there gives access to, which this site reads when you sign in, when it changes, and once a night, to open or close the spaces of a community.')
            && str_contains($html, 'Follows, mutes and space levels: while you are a member of the community.'))
    );
});

test('the privacy policy says that a public space is read by anyone, with the name and the picture (#1387)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('updated', '2026-11-20')
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
        ->where('html', fn (string $html) => str_contains($html, 'and, when you sign in with the sign-in provider of this site (none on this site), the identifier of that account and the date you connected it,')));
});

test('the privacy policy says what Knowii keeps when a manager removes a member, and when it goes away (#1501, approved 2026-11-15)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'If a manager of a community removes you from it, Knowii keeps a record of that community, your account and the date, so that you cannot join it again by yourself; the record is deleted when you accept a new invitation, when a manager lets you join again, when the community is deleted or when you delete your account.')));
});

test('the privacy policy tells about the time zone, the last visit and the visits of spaces (#1476, approved 2026-11-16)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('updated', '2026-11-20')
        ->where('html', fn (string $html) => str_contains($html, 'your time zone, which your browser gives when you sign up or which you set yourself, to show times in your zone; the date of your last visit (a date without an hour, replaced at each visit, that nobody else can see), to show you what is new since then; and your choice to show or hide pop-ups.')
            && str_contains($html, 'and when you saw it), and the date and hour of your last visit of each space, to mark the spaces that have something new for you, and, when you sign in with the sign-in provider of this site')
            && str_contains($html, 'Time zone and pop-up choice: while your account exists. Last visit date: replaced at each visit. Last visit of each space: replaced at each visit, and deleted when you leave the community.'))
    );
});

test('the privacy policy says that a deleted message, post or comment with answers leaves an empty marker (#1565)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('updated', '2026-11-20')
        ->where('html', fn (string $html) => str_contains($html, 'A deleted message, and a deleted post or comment that other people answered, leave an empty marker without their text.')
            && ! str_contains($html, 'This message was deleted'))
    );
});

test('the privacy policy says that the waitlist takes no new address, and how long the old ones stay (#1574)', function () {
    config(['retention.waitlist_months' => 12]);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('updated', '2026-11-20')
        ->where('html', fn (string $html) => str_contains($html, 'Waitlist and newsletter: your email address, if you join the newsletter, or if you joined the waitlist before it closed.')
            && str_contains($html, 'To send the newsletter emails, and the waitlist emails to the addresses given before it closed: your consent (art. 6(1)(a)).')
            && str_contains($html, 'Waitlist: it takes no new address. The addresses given before are deleted after the launch invitation, at most 12 months after you joined it.')
            && ! str_contains($html, 'waitlist forms'))
    );
});

test('the privacy policy names the time of a new sign-in that confirms a deletion (#1521)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('updated', '2026-11-20')
        ->where('html', fn (string $html) => str_contains($html, 'the identifier of that account and the date you connected it, and the time of a new sign-in with it when you confirm the deletion of an account that has no password;')));
});

test('the privacy policy names the events and what they hold in the content of a member (#1513, approved 2026-11-17)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('updated', '2026-11-20')
        ->where('html', fn (string $html) => str_contains($html, 'chat messages, direct messages, pages, lessons, events (their title, description, date, place and meeting address), and uploaded images and their alternative text.'))
    );
});

test('the privacy policy names the headline in the fields of the profile (#1688, approved 2026-11-20)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('updated', '2026-11-20')
        ->where('html', fn (string $html) => str_contains($html, 'Profile: what you choose to add (headline, bio, location, phone number, links to your sites and social accounts), and which of these fields other members see. Your phone number is hidden by default.'))
    );
});

test('the privacy policy tells who sees the member list of a community, and what an entry holds (#148, approved 2026-11-20)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('updated', '2026-11-20')
        ->where('html', fn (string $html) => str_contains($html, 'Members of a community see what you post in it, and the profile fields you choose to show. The members of a community see its member list: your name, your username, your picture, your headline, your role and the date you joined.')
            // The sentence of before the member directory is gone: the list is no longer for the managers only
            && ! str_contains($html, 'The owner and the administrators of a community see its member list.'))
    );
});

test('the privacy policy tells about the answers to events, who sees them and how long they stay (#1515, approved 2026-11-17)', function () {
    config(['retention.event_answers_days' => 90]);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('updated', '2026-11-20')
        ->where('html', fn (string $html) => str_contains($html, 'Your answers to events (yes, replay only or no), until 90 days after the event or until you leave the community.')
            && str_contains($html, 'The host and the team of an event see who answered yes or replay only. They do not see when you open the meeting.')
            && str_contains($html, 'We do not record who attends a meeting.')
            && str_contains($html, 'Answers to events: 90 days after the event, or until you leave the community.'))
    );

    // The period is the one that `retention:prune` applies
    config(['retention.event_answers_days' => 30]);
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'until 30 days after the event or until you leave the community.')));
});

test('the legal texts name the sign-in provider of the instance, and no product name (#1643)', function () {
    config(['services.oidc.issuer' => 'https://accounts.example.com', 'services.oidc.client_id' => 'client', 'services.oidc.client_secret' => 'secret', 'services.oidc.label' => 'Example account']);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'DeveloPassion SRL is the controller of the data of your account on this site.')
            && str_contains($html, 'when you sign in with the sign-in provider of this site (Example account), the identifier of that account')
            && ! str_contains($html, 'Knowii'.' account')));
    $this->get('/dpa')->assertInertia(fn ($page) => $page
        ->where('updated', '2026-11-18')
        ->where('html', fn (string $html) => str_contains($html, 'For the accounts on this site themselves, DeveloPassion SRL is the controller')
            && ! str_contains($html, 'Knowii'.' account')));
});

test('the privacy policy describes the data that comes from other people (#1345)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'Data that we get from other people: when a manager of a community invites you, they give us your email address. When a member mentions you, that mention is content of the community.')));
});

test('the hosted instance has a text for every legal page, and its list of sub-processors (#1653)', function () {
    expect(LegalPages::available())->toBe(array_keys(LegalPages::PAGES))
        ->and(File::isFile(hostedLegalFolder().'/'.LegalPages::SUBPROCESSORS_FILE))->toBeTrue()
        ->and(LegalPages::subprocessors())->toHaveCount(5);
});
