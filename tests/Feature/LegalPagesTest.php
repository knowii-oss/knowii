<?php

use App\Support\LegalPages;
use App\Support\Retention;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * The legal pages (#902, #903, #904, #77): one controller, Markdown rendered on the server, placeholders filled from
 * the configuration (ADR 0030).
 *
 * The texts come only from the folder of `legal.path` (#1653, ADR 0055). These tests read the neutral fixtures of
 * tests/Fixtures/legal (Tests\TestCase points the configuration at them), or a temporary folder of their own
 * (useLegalFolder()) when a file must be missing or wrong. The words of the hosted texts are in HostedLegalTextsTest.
 */
dataset('legal pages', [
    'privacy policy' => ['privacy-policy', 'policy.md', 'Privacy policy'],
    'terms of service' => ['terms-of-service', 'terms.md', 'Terms of service'],
    'data processing agreement' => ['dpa', 'dpa.md', 'Data processing agreement'],
    'imprint' => ['imprint', 'imprint.md', 'Imprint'],
    'content policy' => ['content-policy', 'content-policy.md', 'Content policy'],
    'security' => ['security', 'security.md', 'Security'],
]);

test('the tests read the fixtures, never the texts of an instance (#1653)', function () {
    expect(config('legal.path'))->toBe(base_path('tests/Fixtures/legal'))
        ->and(array_keys(legalTextsWithout()))->toEqualCanonicalizing([...array_map(static fn (string $file): string => $file.'.md', array_values(LegalPages::PAGES)), LegalPages::SUBPROCESSORS_FILE]);
});

test('the default folder of the legal texts is storage/app/legal, and no environment value changes it (#1653)', function () {
    $configuration = (string) file_get_contents(config_path('legal.php'));

    expect((require config_path('legal.php'))['path'])->toBe(storage_path('app/legal'))
        ->and(substr_count($configuration, "'path' => storage_path('app/legal'),"))->toBe(1)
        ->and($configuration)->not->toContain("'subprocessors'");
});

test('the folder of the old texts is gone: the code holds no legal text (#1653)', function () {
    expect(is_dir(resource_path('markdown')))->toBeFalse();
});

test('every legal page is public and rendered on the server', function (string $slug, string $file, string $title) {
    $this->get('/'.$slug)->assertOk()->assertInertia(fn ($page) => $page
        ->component('Legal/Show')
        ->where('title', $title)
        ->where('updated', '2026-01-15')
        ->where('draft', false)
        ->where('html', fn (string $html) => str_contains($html, '<h1>'.$title.'</h1>'))
    );
})->with('legal pages');

test('no page of the fixtures keeps a placeholder', function (string $slug) {
    $this->get('/'.$slug)->assertInertia(fn ($page) => $page->where('html', fn (string $html) => ! str_contains($html, '{{')));
})->with('legal pages');

// ---------------------------------------------------------------------------------------------------------------------
// A missing text (#1653)
// ---------------------------------------------------------------------------------------------------------------------

test('a legal page without its file answers 404, and the other pages stay', function (string $slug, string $file) {
    $this->useLegalFolder(legalTextsWithout($file));

    expect(LegalPages::exists($slug))->toBeFalse();
    $this->get('/'.$slug)->assertNotFound();

    foreach (array_keys(LegalPages::PAGES) as $other) {
        if ($other !== $slug) {
            $this->get('/'.$other)->assertOk();
        }
    }
})->with('legal pages');

test('an instance with an empty folder serves no legal page', function (string $slug) {
    $this->useLegalFolder();

    $this->get('/'.$slug)->assertNotFound();
})->with('legal pages');

test('an instance without the folder serves no legal page', function () {
    $folder = $this->useLegalFolder();
    File::deleteDirectory($folder);

    expect(LegalPages::available())->toBe([])
        ->and(LegalPages::subprocessors())->toBe([])
        ->and(LegalPages::updated('dpa'))->toBeNull();
    $this->get('/privacy-policy')->assertNotFound();
});

test('a file that appears is served at the next request, and a file that goes away is not', function () {
    $folder = $this->useLegalFolder();
    $this->get('/imprint')->assertNotFound();

    File::put($folder.'/imprint.md', "---\ntitle: Imprint\n---\n\n# Imprint\n\nExample Ltd");

    $this->get('/imprint')->assertOk()->assertInertia(fn ($page) => $page
        ->where('title', 'Imprint')
        ->where('html', fn (string $html) => str_contains($html, 'Example Ltd')));

    File::delete($folder.'/imprint.md');

    $this->get('/imprint')->assertNotFound();
});

test('every route of a legal page stays registered without its file: the front end and the route cache need the name', function (string $slug) {
    $this->useLegalFolder();

    expect(route($slug.'.show', [], false))->toBe('/'.$slug);
})->with('legal pages');

test('the pages that exist are listed in the order of the pages', function () {
    expect(LegalPages::available())->toBe(['privacy-policy', 'terms-of-service', 'dpa', 'imprint', 'content-policy', 'security']);

    $this->useLegalFolder(legalTextsWithout('terms.md', 'imprint.md', 'security.md'));

    expect(LegalPages::available())->toBe(['privacy-policy', 'dpa', 'content-policy'])
        ->and(LegalPages::exists('privacy-policy'))->toBeTrue()
        ->and(LegalPages::exists('terms-of-service'))->toBeFalse()
        ->and(LegalPages::exists('no-such-page'))->toBeFalse();

    // The list of the sub-processors is not a page
    $this->useLegalFolder(['subprocessors.json' => '[]', 'notes.md' => '# Notes', 'policy.txt' => 'Text']);

    expect(LegalPages::available())->toBe([]);
});

test('every page shares the legal pages that exist, so that the screens hide the links of the others', function () {
    $this->get('/login')->assertInertia(fn ($page) => $page
        ->where('legalPages', ['privacy-policy', 'terms-of-service', 'dpa', 'imprint', 'content-policy', 'security']));

    $this->useLegalFolder(legalTextsWithout('policy.md', 'dpa.md'));

    $this->get('/login')->assertInertia(fn ($page) => $page
        ->where('legalPages', ['terms-of-service', 'imprint', 'content-policy', 'security']));

    $this->useLegalFolder();

    $this->get('/login')->assertInertia(fn ($page) => $page->where('legalPages', []));
});

test('the front end knows the same legal pages, in the same order', function () {
    $constants = (string) file_get_contents(base_path('libs/common/src/lib/constants.ts'));
    $slugs = implode(', ', array_map(static fn (string $slug): string => "'".$slug."'", array_keys(LegalPages::PAGES)));

    expect($constants)->toContain('export const LEGAL_PAGE_SLUGS = ['.$slugs.'] as const;');
});

// ---------------------------------------------------------------------------------------------------------------------
// The front matter
// ---------------------------------------------------------------------------------------------------------------------

test('the front matter gives the title, the date and the draft notice, and the text is escaped', function () {
    $this->useLegalFolder(['policy.md' => '---
title: Our privacy
updated: 2027-01-01
draft: true
---

# Our privacy

Example <b>Ltd</b>, {{retention.session}}.']);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('title', 'Our privacy')
        ->where('draft', true)
        ->where('updated', '2027-01-01')
        ->where('html', fn (string $html) => str_contains($html, 'Example &lt;b&gt;Ltd&lt;/b&gt;, 2 hours.'))
    );
});

test('a copied template keeps its draft notice: a byte order mark, a comment after a value and a date that is a mark', function () {
    // An editor can add a byte order mark; the operator can write a note after a value; the date of a template is a mark
    $this->useLegalFolder(['policy.md' => "\xEF\xBB\xBF---\ntitle: Our privacy\nupdated: [TODO: the date]\ndraft: true # until the lawyer read it\n---\n\n# Our privacy\n\nText."]);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('title', 'Our privacy')
        ->where('draft', true)
        ->where('updated', null)
        ->where('html', fn (string $html) => str_contains($html, '<h1>Our privacy</h1>') && ! str_contains($html, 'draft:'))
    );
    expect(LegalPages::updated('privacy-policy'))->toBeNull();
});

test('a text that is not UTF-8 is still served, and a file of the sub-processors with a byte order mark is read', function () {
    $this->useLegalFolder([
        'policy.md' => "# Caf\xE9 rules\n\n{{subprocessors}}",
        'subprocessors.json' => "\xEF\xBB\xBF".json_encode([['name' => 'Example Hosting', 'country' => 'Ireland', 'purpose' => 'Servers', 'safeguard' => 'None needed']]),
    ]);
    Log::spy();

    $this->get('/privacy-policy')->assertOk()->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'Caf') && str_contains($html, 'Example Hosting'))
    );
    Log::shouldNotHaveReceived('warning');
});

test('a text without a front matter has the name of its page as title, no date, and is not a draft', function () {
    $this->useLegalFolder(['content-policy.md' => "# Rules\n\nBe kind."]);

    $this->get('/content-policy')->assertInertia(fn ($page) => $page
        ->where('title', 'Content policy')
        ->where('updated', null)
        ->where('draft', false)
        ->where('html', fn (string $html) => str_contains($html, '<h1>Rules</h1>'))
    );
});

test('the version of a legal page is its updated date (#1353)', function () {
    expect(LegalPages::updated('dpa'))->toBe('2026-01-15');
});

test('a page has no version without a file, without a date, or with a date that is not YYYY-MM-DD', function (?string $contents) {
    $this->useLegalFolder($contents === null ? [] : ['dpa.md' => $contents]);

    expect(LegalPages::updated('dpa'))->toBeNull();
})->with([
    'no file' => [null],
    'no front matter' => ["# Agreement\n"],
    'no date' => ["---\ntitle: Agreement\ndraft: true\n---\n\n# Agreement\n"],
    'an empty date' => ["---\ntitle: Agreement\nupdated:\n---\n\n# Agreement\n"],
    'a date in words' => ["---\nupdated: 15 January 2026\n---\n\n# Agreement\n"],
    'a date in another order' => ["---\nupdated: 15-01-2026\n---\n\n# Agreement\n"],
    'a date with an hour' => ["---\nupdated: 2026-01-15 10:00\n---\n\n# Agreement\n"],
    'a day that does not exist' => ["---\nupdated: 2026-02-30\n---\n\n# Agreement\n"],
    'a mark of a template' => ["---\nupdated: [TODO: the date]\n---\n\n# Agreement\n"],
]);

test('a date in quotes is a version', function () {
    $this->useLegalFolder(['dpa.md' => "---\nupdated: \"2027-03-04\"\n---\n\n# Agreement\n"]);

    expect(LegalPages::updated('dpa'))->toBe('2027-03-04');
});

test('the version of an unknown page is an error of the code, not a missing file', function () {
    expect(fn () => LegalPages::updated('no-such-page'))->toThrow(InvalidArgumentException::class);
});

// ---------------------------------------------------------------------------------------------------------------------
// The placeholders
// ---------------------------------------------------------------------------------------------------------------------

test('a text shows the operator, the contact address and the site of the instance (#1653)', function () {
    config(['legal.operator' => 'Example Ltd (Ireland)', 'legal.contact_email' => 'hello@example.com', 'app.url' => 'https://community.example.com:8443/path']);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'Example Ltd (Ireland) runs this site (community.example.com). Contact: <a href="mailto:hello@example.com">hello@example.com</a>.')));

    expect(LegalPages::fillPlaceholders('{{operator}}|{{contact}}|{{site}}|{{ operator }}'))->toBe('Example Ltd (Ireland)|hello@example.com|community.example.com|Example Ltd (Ireland)');
});

test('the site is empty when the address of the instance has no host', function () {
    config(['app.url' => null]);

    expect(LegalPages::fillPlaceholders('[{{site}}]'))->toBe('[]');
});

test('a text shows the retention periods of the configuration (ADR 0030)', function () {
    config(['session.lifetime' => 45, 'retention.waitlist_months' => 6, 'retention.expired_api_tokens_days' => 20]);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'Sessions: 45 minutes after your last activity')
            && str_contains($html, 'Waitlist: at most 6 months.')
            && str_contains($html, 'Expired tokens are deleted 20 days after their expiry date.')
            && str_contains($html, '<td>45 minutes after your last activity</td>'))
    );
    $this->get('/security')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'valid for 15 minutes and work once') && str_contains($html, 'expire after 90 days by default')));
});

test('a text shows the session cookie of the configuration (#1342)', function () {
    config(['session.cookie' => 'example_session']);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<td><code>example_session</code></td>')));
});

test('a text names the sign-in provider of the instance, or says that there is none (#1643)', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'The sign-in provider of this site: none on this site.')));

    config(['services.oidc.issuer' => 'https://accounts.example.com', 'services.oidc.client_id' => 'client', 'services.oidc.client_secret' => 'secret', 'services.oidc.label' => 'Example account']);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'The sign-in provider of this site: Example account.')));
});

test('every known placeholder is filled', function () {
    $names = ['subprocessors', 'subprocessors.community', 'cookies.session', 'sign_in_provider', 'operator', 'contact', 'site'];
    foreach (array_keys(Retention::periods()) as $period) {
        $names[] = 'retention.'.$period;
    }

    // The fixtures use each one, so that a placeholder that the code drops fails here
    $fixtures = implode("\n", legalTextsWithout(LegalPages::SUBPROCESSORS_FILE));
    foreach ($names as $name) {
        expect($fixtures)->toContain('{{'.$name.'}}')
            ->and(LegalPages::fillPlaceholders('{{'.$name.'}}'))->not->toContain('{{');
    }
});

test('an unknown placeholder stays visible', function () {
    expect(LegalPages::fillPlaceholders('{{operator}} and {{no_such_value}} and {{retention.no_such_period}} and {{ Operator }}'))
        ->toBe('Example Operator and {{no_such_value}} and {{retention.no_such_period}} and {{ Operator }}');

    $this->useLegalFolder(['imprint.md' => "# Imprint\n\n{{company_number}}"]);

    $this->get('/imprint')->assertInertia(fn ($page) => $page->where('html', fn (string $html) => str_contains($html, '{{company_number}}')));
});

// ---------------------------------------------------------------------------------------------------------------------
// The sub-processors (#904, #1352, #1653)
// ---------------------------------------------------------------------------------------------------------------------

test('the sub-processors come from the file beside the texts, in its order', function () {
    expect(LegalPages::subprocessors())->toBe([
        ['name' => 'Example Hosting GmbH', 'country' => 'Germany', 'purpose' => 'Hosting of the servers', 'safeguard' => 'European Union'],
        ['name' => 'Example Mail, Inc.', 'country' => 'United States', 'purpose' => 'Emails of the service', 'safeguard' => 'Standard contractual clauses'],
        ['name' => 'Example Statistics OÜ', 'country' => 'Estonia', 'purpose' => 'Audience statistics', 'safeguard' => 'European Union'],
        ['name' => 'Example Archive Ltd', 'country' => 'Ireland', 'purpose' => 'Storage of the backups', 'safeguard' => 'European Union'],
        ['name' => 'Example AI, Inc.', 'country' => 'United States', 'purpose' => 'Summaries of posts. Only for the communities whose owner turns on AI summaries.', 'safeguard' => 'Standard contractual clauses'],
    ]);
});

test('the configuration holds no sub-processor: a list there changes nothing', function () {
    config(['legal.subprocessors' => [['name' => 'From The Configuration Ltd', 'country' => 'Ireland', 'purpose' => 'Nothing', 'safeguard' => 'European Union']]]);

    expect(array_column(LegalPages::subprocessors(), 'name'))->not->toContain('From The Configuration Ltd')->toHaveCount(5);
});

test('the privacy policy lists every sub-processor, the DPA only those of community data (#1352)', function () {
    expect(array_column(LegalPages::subprocessors(LegalPages::SCOPE_COMMUNITY), 'name'))->toBe(['Example Hosting GmbH', 'Example Mail, Inc.', 'Example Archive Ltd', 'Example AI, Inc.'])
        ->and(array_column(LegalPages::subprocessors(LegalPages::SCOPE_PLATFORM), 'name'))->toBe(['Example Hosting GmbH', 'Example Mail, Inc.', 'Example Statistics OÜ', 'Example Archive Ltd']);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<th>Sub-processor</th>')
            && str_contains($html, '<th>Transfer safeguard</th>')
            && str_contains($html, '<td>Example Hosting GmbH</td>')
            && str_contains($html, '<td>Example Statistics OÜ</td>')
            && str_contains($html, '<td>Example AI, Inc.</td>'))
    );
    $this->get('/dpa')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<td>Example Hosting GmbH</td>')
            && str_contains($html, '<td>Example Mail, Inc.</td>')
            && ! str_contains($html, 'Example Statistics'))
    );
});

test('a sub-processor without a scope is in every list (#1352)', function () {
    $this->useLegalFolder(['subprocessors.json' => (string) json_encode([
        ['name' => 'No Scope GmbH', 'country' => 'Germany', 'purpose' => 'Hosting', 'safeguard' => 'European Union'],
        ['name' => 'Platform Only Ltd', 'country' => 'Ireland', 'purpose' => 'Statistics', 'safeguard' => 'European Union', 'scope' => ['platform']],
    ])]);

    expect(array_column(LegalPages::subprocessors(LegalPages::SCOPE_COMMUNITY), 'name'))->toBe(['No Scope GmbH'])
        ->and(array_column(LegalPages::subprocessors(LegalPages::SCOPE_PLATFORM), 'name'))->toBe(['No Scope GmbH', 'Platform Only Ltd'])
        ->and(array_column(LegalPages::subprocessors(), 'name'))->toBe(['No Scope GmbH', 'Platform Only Ltd']);
});

test('a sub-processor of an optional feature says that only the communities that turn it on use it (#904)', function () {
    $this->get('/dpa')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<td>Summaries of posts. Only for the communities whose owner turns on AI summaries.</td>'))
    );
});

test('the table keeps a vertical bar of a value in its cell', function () {
    $this->useLegalFolder(['subprocessors.json' => (string) json_encode([
        ['name' => 'Example | Hosting', 'country' => 'Germany', 'purpose' => 'Hosting', 'safeguard' => 'European Union'],
    ])]);

    expect(LegalPages::subprocessorTable())->toBe("| Sub-processor | Country | Purpose | Transfer safeguard |\n| --- | --- | --- | --- |\n| Example \\| Hosting | Germany | Hosting | European Union |");
});

test('an instance without the file of the sub-processors has an empty list, and no warning', function () {
    Log::spy();
    $this->useLegalFolder(legalTextsWithout(LegalPages::SUBPROCESSORS_FILE));

    expect(LegalPages::subprocessors())->toBe([])
        ->and(LegalPages::subprocessors(LegalPages::SCOPE_COMMUNITY))->toBe([]);
    $this->get('/privacy-policy')->assertOk()->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<th>Sub-processor</th>') && ! str_contains($html, 'Example Hosting')));

    Log::shouldNotHaveReceived('warning');
});

dataset('files that are not a JSON list', [
    'text that is not JSON' => ['Example Hosting GmbH, Germany'],
    'an empty file' => [''],
    'an object' => ['{"name": "Example Hosting GmbH", "country": "Germany"}'],
    'a text value' => ['"Example Hosting GmbH"'],
    'a number' => ['42'],
    'null' => ['null'],
]);

test('a file of the sub-processors that is not a JSON list gives an empty list and one warning', function (string $contents) {
    $folder = $this->useLegalFolder(['subprocessors.json' => $contents]);
    Log::spy();

    expect(LegalPages::subprocessors())->toBe([]);

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'not a JSON list') && $context === ['file' => $folder.'/subprocessors.json']);
})->with('files that are not a JSON list');

test('a page that shows the sub-processors twice reads a wrong file once: one warning, and two empty tables', function (string $contents) {
    $this->useLegalFolder(['subprocessors.json' => $contents, 'dpa.md' => "# Agreement\n\n{{subprocessors.community}}\n\n{{subprocessors}}"]);
    Log::spy();

    $this->get('/dpa')->assertOk()->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => substr_count($html, '<th>Sub-processor</th>') === 2 && ! str_contains($html, '<td>')));

    Log::shouldHaveReceived('warning')->once();
})->with('files that are not a JSON list');

test('a text that does not show the sub-processors does not read their file', function () {
    $this->useLegalFolder(['subprocessors.json' => 'not JSON', 'imprint.md' => "# Imprint\n\n{{operator}}"]);
    Log::spy();

    $this->get('/imprint')->assertOk();

    Log::shouldNotHaveReceived('warning');
});

test('an entry of the sub-processors that is not an object is left out, and a value that is not text is empty', function () {
    $this->useLegalFolder(['subprocessors.json' => '[
        "Example Hosting GmbH",
        42,
        null,
        ["Example Mail, Inc.", "United States"],
        [],
        {"name": "Example Statistics Ltd", "country": 7, "purpose": null, "safeguard": ["European Union"], "scope": "platform", "feature": false},
        {"name": "Example Archive Ltd", "country": "Ireland", "purpose": "Backups", "safeguard": "European Union", "scope": ["community", 3]}
    ]']);
    Log::spy();

    expect(LegalPages::subprocessors())->toBe([
        ['name' => 'Example Statistics Ltd', 'country' => '', 'purpose' => '', 'safeguard' => ''],
        ['name' => 'Example Archive Ltd', 'country' => 'Ireland', 'purpose' => 'Backups', 'safeguard' => 'European Union'],
    ])
        // A scope that is not a list is no scope: the entry is in every list
        ->and(array_column(LegalPages::subprocessors(LegalPages::SCOPE_COMMUNITY), 'name'))->toBe(['Example Statistics Ltd', 'Example Archive Ltd'])
        ->and(array_column(LegalPages::subprocessors(LegalPages::SCOPE_PLATFORM), 'name'))->toBe(['Example Statistics Ltd']);

    Log::shouldNotHaveReceived('warning');
});

test('an empty list of sub-processors is a list: no warning', function () {
    $this->useLegalFolder(['subprocessors.json' => '[]']);
    Log::spy();

    expect(LegalPages::subprocessors())->toBe([]);

    Log::shouldNotHaveReceived('warning');
});

// ---------------------------------------------------------------------------------------------------------------------
// What the code promises beside the texts
// ---------------------------------------------------------------------------------------------------------------------

test('the "Remember me" cookie lasts as long as the privacy policy says (#1342)', function () {
    $guard = auth()->guard('web');
    $minutes = (fn (): int => $this->getRememberDuration())->call($guard);

    expect(intdiv($minutes, 60 * 24))->toBe(400);
});
