<?php

use App\Constants;
use App\Enums\KnowiiCommunityMemberRole;
use App\Exceptions\InstanceNotConfiguredException;
use App\Http\Middleware\RefuseUnconfiguredInstance;
use App\Mail\CommunityInvitation as CommunityInvitationMail;
use App\Models\User;
use App\Support\InstanceValues;
use Bepsvpt\SecureHeaders\SecureHeadersMiddleware;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/*
 * The values of one instance come from configuration (#1653, ADR 0055): the operator and its contact address are required
 * in production, where the instance refuses to start without them. The publisher, the footer and the social links, the
 * structured data and the first user of the seeder are optional, and an empty value hides what it feeds.
 */

const INSTANCE_NOT_CONFIGURED = 'This instance is not configured. Set LEGAL_OPERATOR and LEGAL_CONTACT_EMAIL in the environment. See SELF_HOSTING.md, section "Instance values".';

/**
 * A configuration file, read again with these environment values (null: the name is not set at all). The environment of
 * the test run comes back afterwards.
 *
 * @param  array<string, string|null>  $environment
 * @return array<string, mixed>
 */
function instanceConfigFile(string $file, array $environment): array
{
    $before = [];
    foreach ($environment as $name => $value) {
        $before[$name] = [
            'env' => array_key_exists($name, $_ENV) ? $_ENV[$name] : null,
            'server' => array_key_exists($name, $_SERVER) ? $_SERVER[$name] : null,
            'process' => getenv($name),
        ];
        unset($_ENV[$name], $_SERVER[$name]);
        $value === null ? putenv($name) : putenv("{$name}={$value}");
    }

    try {
        return require config_path($file);
    } finally {
        foreach ($before as $name => $values) {
            $values['process'] === false ? putenv($name) : putenv("{$name}={$values['process']}");
            if ($values['env'] !== null) {
                $_ENV[$name] = $values['env'];
            }
            if ($values['server'] !== null) {
                $_SERVER[$name] = $values['server'];
            }
        }
    }
}

/**
 * The messages that the application logs from now on, as "level: message context"
 */
function instanceLogs(): ArrayObject
{
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = $event->level.': '.$event->message.' '.json_encode($event->context, JSON_UNESCAPED_SLASHES);
    });

    return $logged;
}

/**
 * The structured data of a page, decoded, with the raw text of its block
 *
 * @return array{data: array<string, mixed>, raw: string}
 */
function instanceStructuredData(string $html): array
{
    expect(preg_match('#<script id="site-microdata-script" type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $found))->toBe(1);

    return ['data' => json_decode($found[1], true, 16, JSON_THROW_ON_ERROR), 'raw' => $found[1]];
}

// --- Rules 1 and 2: the two required values, and their placeholders outside production ------------------------------------

test('outside production, an operator and a contact address that are not set get the neutral placeholders', function (?string $operator, ?string $contactEmail) {
    $legal = instanceConfigFile('legal.php', ['APP_ENV' => 'testing', 'LEGAL_OPERATOR' => $operator, 'LEGAL_CONTACT_EMAIL' => $contactEmail]);

    expect($legal['operator'])->toBe('Example Operator')->toBe(Constants::INSTANCE_PLACEHOLDER_OPERATOR)
        ->and($legal['contact_email'])->toBe('contact@example.com')->toBe(Constants::INSTANCE_PLACEHOLDER_CONTACT_EMAIL);
})->with([
    'not set' => [null, null],
    'empty (KEY= in .env)' => ['', ''],
    'blank' => ['   ', "\t "],
]);

test('in production, an operator and a contact address that are not set stay empty: no default names anybody', function (?string $environment, ?string $operator, ?string $contactEmail) {
    $legal = instanceConfigFile('legal.php', ['APP_ENV' => $environment, 'LEGAL_OPERATOR' => $operator, 'LEGAL_CONTACT_EMAIL' => $contactEmail]);

    expect($legal['operator'])->toBe('')
        ->and($legal['contact_email'])->toBe('');
})->with([
    'not set' => ['production', null, null],
    'empty (KEY= in .env)' => ['production', '', ''],
    'blank' => ['production', '   ', "\t "],
    'no APP_ENV at all is production' => [null, null, null],
]);

test('a configured operator and contact address are used in every environment, without the spaces around', function (string $environment) {
    $legal = instanceConfigFile('legal.php', ['APP_ENV' => $environment, 'LEGAL_OPERATOR' => '  Example Ltd (Ireland) ', 'LEGAL_CONTACT_EMAIL' => ' hello@example.com ']);

    expect($legal['operator'])->toBe('Example Ltd (Ireland)')
        ->and($legal['contact_email'])->toBe('hello@example.com');
})->with(['testing', 'local', 'production']);

test('the test environment runs with the placeholders, and nothing is missing outside production', function () {
    config(['legal.operator' => Constants::INSTANCE_PLACEHOLDER_OPERATOR, 'legal.contact_email' => Constants::INSTANCE_PLACEHOLDER_CONTACT_EMAIL]);
    expect(app()->isProduction())->toBeFalse()
        ->and(InstanceValues::operator())->toBe('Example Operator')
        ->and(InstanceValues::contactEmail())->toBe('contact@example.com')
        ->and(InstanceValues::missing())->toBe([]);

    config(['legal.operator' => '', 'legal.contact_email' => '']);
    expect(InstanceValues::missing())->toBe([]);
});

// --- Rules 3 and 4: what is missing in production, and the message ---------------------------------------------------------

test('in production, a required value is missing when it is empty, blank, the placeholder or not an address', function (?string $operator, ?string $contactEmail, array $missing) {
    config(['legal.operator' => $operator, 'legal.contact_email' => $contactEmail]);
    $this->app->detectEnvironment(fn () => 'production');

    expect(InstanceValues::missing())->toBe($missing);
})->with([
    'both empty' => ['', '', ['LEGAL_OPERATOR', 'LEGAL_CONTACT_EMAIL']],
    'both not set' => [null, null, ['LEGAL_OPERATOR', 'LEGAL_CONTACT_EMAIL']],
    'both blank' => ['   ', " \t", ['LEGAL_OPERATOR', 'LEGAL_CONTACT_EMAIL']],
    'both placeholders (a copied example file)' => ['Example Operator', 'contact@example.com', ['LEGAL_OPERATOR', 'LEGAL_CONTACT_EMAIL']],
    'both placeholders in another letter case' => ['example operator', 'Contact@Example.COM', ['LEGAL_OPERATOR', 'LEGAL_CONTACT_EMAIL']],
    'the operator is the placeholder' => ['Example Operator', 'hello@example.com', ['LEGAL_OPERATOR']],
    'the address is the placeholder' => ['Example Ltd (Ireland)', 'contact@example.com', ['LEGAL_CONTACT_EMAIL']],
    'the operator is empty' => ['', 'hello@example.com', ['LEGAL_OPERATOR']],
    'the address is empty' => ['Example Ltd (Ireland)', '', ['LEGAL_CONTACT_EMAIL']],
    'the address is not an address' => ['Example Ltd (Ireland)', 'hello at example.com', ['LEGAL_CONTACT_EMAIL']],
    'the address is a web address' => ['Example Ltd (Ireland)', 'https://example.com/contact', ['LEGAL_CONTACT_EMAIL']],
    'both set' => ['Example Ltd (Ireland)', 'hello@example.com', []],
    'both set, with spaces around' => [' Example Ltd (Ireland) ', ' hello@example.com ', []],
]);

test('the message names the values to set, joined by "and"', function () {
    expect(InstanceValues::notConfiguredMessage(['LEGAL_OPERATOR', 'LEGAL_CONTACT_EMAIL']))->toBe(INSTANCE_NOT_CONFIGURED)
        ->and(InstanceValues::notConfiguredMessage(['LEGAL_OPERATOR']))
        ->toBe('This instance is not configured. Set LEGAL_OPERATOR in the environment. See SELF_HOSTING.md, section "Instance values".')
        ->and(InstanceValues::notConfiguredMessage(['LEGAL_CONTACT_EMAIL']))
        ->toBe('This instance is not configured. Set LEGAL_CONTACT_EMAIL in the environment. See SELF_HOSTING.md, section "Instance values".');
});

// --- Rule 6: the web server refuses every request ----------------------------------------------------------------------------

test('the refusal runs before every middleware of the application and of the framework', function () {
    $global = app(HttpKernel::class)->getGlobalMiddleware();
    $position = array_search(RefuseUnconfiguredInstance::class, $global, true);

    expect($position)->not->toBeFalse();
    // Only the security headers, which read only the configuration and are on the 503 too, and a middleware that a package
    // prepends after the application can stand before it; it must not be one of ours
    $before = array_slice($global, 0, (int) $position);
    expect($before)->toContain(SecureHeadersMiddleware::class);
    foreach ($before as $middleware) {
        expect($middleware)->not->toStartWith('App\\')->not->toStartWith('Illuminate\\');
    }
    expect((int) $position)->toBeLessThanOrEqual(2);
});

test('in production, the refusal is not stored by a proxy, and the log gets the message once a minute', function () {
    config(['legal.operator' => '', 'legal.contact_email' => '']);
    $this->app->detectEnvironment(fn () => 'production');
    $logged = instanceLogs();

    $first = $this->get('/up');
    $this->get('/')->assertStatus(503);
    $this->get('/login')->assertStatus(503);

    expect($first->headers->get('Cache-Control'))->toContain('no-store')
        ->and($logged->getArrayCopy())->toBe(['critical: '.INSTANCE_NOT_CONFIGURED.' []']);

    $this->travel(61)->seconds();
    $this->get('/')->assertStatus(503);
    expect($logged)->toHaveCount(2);
});

test('the example file of production is refused as it is: its two required values are empty', function () {
    $lines = file(base_path('.env.production.example'), FILE_IGNORE_NEW_LINES) ?: [];

    expect($lines)->toContain('LEGAL_OPERATOR=')->toContain('LEGAL_CONTACT_EMAIL=');
});

test('in production, an instance that is not configured answers 503 with the message on every address, the health address too', function (string $method, string $url) {
    config(['legal.operator' => '', 'legal.contact_email' => '']);
    $this->app->detectEnvironment(fn () => 'production');
    $logged = instanceLogs();

    $response = $this->call($method, $url);

    $response->assertStatus(503)->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())->toBe(INSTANCE_NOT_CONFIGURED)
        ->and($logged->getArrayCopy())->toBe(['critical: '.INSTANCE_NOT_CONFIGURED.' []']);
})->with([
    'the home page' => ['GET', '/'],
    'the health address' => ['GET', '/up'],
    'an API address' => ['GET', '/api/v1/communities'],
    'an API change' => ['POST', '/api/v1/auth/login'],
    'the sign-in page' => ['GET', '/login'],
    'an address that matches no route' => ['GET', '/no-such-address'],
]);

test('in production, the answer names only the value that is missing', function () {
    config(['legal.operator' => 'Example Ltd (Ireland)', 'legal.contact_email' => 'contact@example.com']);
    $this->app->detectEnvironment(fn () => 'production');

    $response = $this->get('/up')->assertStatus(503);

    expect($response->getContent())->toBe('This instance is not configured. Set LEGAL_CONTACT_EMAIL in the environment. See SELF_HOSTING.md, section "Instance values".');
});

test('in production, a configured instance answers', function () {
    configuredInstance();
    $this->app->detectEnvironment(fn () => 'production');
    $logged = instanceLogs();

    $this->get('/up')->assertOk();
    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Welcome'));
    expect(array_filter($logged->getArrayCopy(), static fn (string $line): bool => str_starts_with($line, 'critical:')))->toBe([]);
});

test('outside production, an instance without the two values is not refused', function () {
    config(['legal.operator' => '', 'legal.contact_email' => '']);

    $this->get('/up')->assertOk();
    $this->get('/')->assertOk();
    $this->getJson('/api/v1/no-such-endpoint')->assertNotFound();
});

// --- Rules 7 and 8: the long-running processes refuse to start, the other commands run ---------------------------------------

test('in production, a long-running process refuses to start while the instance is not configured', function (string $command) {
    config(['legal.operator' => '', 'legal.contact_email' => '']);
    $this->app->detectEnvironment(fn () => 'production');

    expect(fn () => event(new CommandStarting($command, new ArrayInput([]), new NullOutput)))
        ->toThrow(InstanceNotConfiguredException::class, INSTANCE_NOT_CONFIGURED);
})->with(Constants::INSTANCE_CHECKED_COMMANDS);

test('the checked commands are the queue worker, the scheduler and the WebSocket server', function () {
    expect(Constants::INSTANCE_CHECKED_COMMANDS)->toBe(['queue:work', 'queue:listen', 'schedule:work', 'schedule:run', 'reverb:start'])
        ->and(new InstanceNotConfiguredException('x'))->toBeInstanceOf(RuntimeException::class);
});

test('in production, every other command runs while the instance is not configured', function (string $command) {
    config(['legal.operator' => '', 'legal.contact_email' => '']);
    $this->app->detectEnvironment(fn () => 'production');

    event(new CommandStarting($command, new ArrayInput([]), new NullOutput));

    expect(InstanceValues::missing())->not->toBe([]);
})->with(['migrate', 'config:cache', 'key:generate', 'app:health', 'storage:link', 'package:discover', 'route:cache', 'view:cache', 'event:cache', 'optimize', 'about']);

test('a long-running process starts in production when the instance is configured, and outside production without the values', function (string $command) {
    configuredInstance();
    $this->app->detectEnvironment(fn () => 'production');
    event(new CommandStarting($command, new ArrayInput([]), new NullOutput));

    config(['legal.operator' => '', 'legal.contact_email' => '']);
    $this->app->detectEnvironment(fn () => 'testing');
    event(new CommandStarting($command, new ArrayInput([]), new NullOutput));

    expect(InstanceValues::missing())->toBe([]);
})->with(Constants::INSTANCE_CHECKED_COMMANDS);

// --- Rules 9 to 11: the optional values --------------------------------------------------------------------------------------

test('the optional values are empty by default, and the seeder has a neutral address', function () {
    $instance = instanceConfigFile('instance.php', [
        'INSTANCE_PUBLISHER_NAME' => null, 'INSTANCE_PUBLISHER_URL' => '', 'INSTANCE_FOOTER_NOTE' => '  ',
        'INSTANCE_FOOTER_LINKS' => null, 'INSTANCE_SOCIAL_LINKS' => null, 'SEED_ADMIN_EMAIL' => '',
    ]);

    expect($instance)->toBe([
        'publisher' => ['name' => '', 'url' => ''],
        'footer' => ['note' => '', 'links' => ''],
        'social_links' => '',
        'seed' => ['admin_email' => 'admin@example.com'],
    ]);
});

test('the optional values are read from the environment as trimmed text', function () {
    $instance = instanceConfigFile('instance.php', [
        'INSTANCE_PUBLISHER_NAME' => ' Example Ltd ', 'INSTANCE_PUBLISHER_URL' => 'https://example.com', 'INSTANCE_FOOTER_NOTE' => 'Made in Ireland.',
        'INSTANCE_FOOTER_LINKS' => 'Blog=https://blog.example.com', 'INSTANCE_SOCIAL_LINKS' => 'github=https://github.com/example',
        'SEED_ADMIN_EMAIL' => ' first@example.com ',
    ]);

    expect($instance)->toBe([
        'publisher' => ['name' => 'Example Ltd', 'url' => 'https://example.com'],
        'footer' => ['note' => 'Made in Ireland.', 'links' => 'Blog=https://blog.example.com'],
        'social_links' => 'github=https://github.com/example',
        'seed' => ['admin_email' => 'first@example.com'],
    ]);
});

test('the footer links are "Label=https://address" entries, split at the first "="', function () {
    config(['instance.footer.links' => ' Example Community = https://community.example.com , Blog=https://blog.example.com/?tag=news&page=2,,']);
    $logged = instanceLogs();

    expect(InstanceValues::footerLinks())->toBe([
        ['label' => 'Example Community', 'href' => 'https://community.example.com'],
        ['label' => 'Blog', 'href' => 'https://blog.example.com/?tag=news&page=2'],
    ])->and($logged->getArrayCopy())->toBe([]);
});

test('a wrong footer link is left out and logged', function (string $entry) {
    config(['instance.footer.links' => 'Blog=https://blog.example.com,'.$entry.',Forum=https://forum.example.com']);
    $logged = instanceLogs();

    expect(InstanceValues::footerLinks())->toBe([
        ['label' => 'Blog', 'href' => 'https://blog.example.com'],
        ['label' => 'Forum', 'href' => 'https://forum.example.com'],
    ])->and($logged->getArrayCopy())->toBe([
        'warning: Instance values: a footer link is left out because it is not "Label=https://address" '.json_encode(['value' => trim($entry)], JSON_UNESCAPED_SLASHES),
    ]);
})->with([
    'an empty label' => ['=https://nolabel.example.com'],
    'a blank label' => ['  =https://nolabel.example.com'],
    'an empty address' => ['Shop='],
    'an http address' => ['Shop=http://shop.example.com'],
    'a script address' => ['Shop=javascript:alert(1)'],
    'a mail address' => ['Shop=mailto:shop@example.com'],
    'an address without a host' => ['Shop=https://'],
    'an address with a space' => ['Shop=https://shop.example.com/a b'],
]);

test('an entry that a comma of its address cuts in two is left out as a whole, never shown as half an address', function () {
    config([
        'instance.footer.links' => 'Blog=https://blog.example.com,Docs=https://example.com/a,b,Forum=https://forum.example.com,Guide=https://example.com/x%2Cy',
        'instance.social_links' => 'github=https://github.com/example,youtube=https://video.example.com/a,b',
    ]);
    $logged = instanceLogs();

    expect(InstanceValues::footerLinks())->toBe([
        ['label' => 'Blog', 'href' => 'https://blog.example.com'],
        ['label' => 'Forum', 'href' => 'https://forum.example.com'],
        ['label' => 'Guide', 'href' => 'https://example.com/x%2Cy'],
    ])->and(InstanceValues::socialLinks())->toBe([
        ['property' => 'social_link_github', 'href' => 'https://github.com/example'],
    ])->and($logged->getArrayCopy())->toBe([
        'warning: Instance values: a footer link is left out because it is not "Label=https://address" {"value":"Docs=https://example.com/a,b"}',
        'warning: Instance values: a social link is left out because it is not "key=https://address" with a known key {"value":"youtube=https://video.example.com/a,b"}',
    ]);

    // A part without "=" at the start of the list has no entry before it
    config(['instance.footer.links' => 'Shop,Blog=https://blog.example.com']);
    expect(InstanceValues::footerLinks())->toBe([['label' => 'Blog', 'href' => 'https://blog.example.com']]);
});

test('a second footer link with the same label and a social link that is in the list twice are left out', function () {
    config([
        'instance.footer.links' => 'Blog=https://blog.example.com,Blog=https://other.example.com,Forum=https://forum.example.com',
        'instance.social_links' => 'github=https://github.com/example,GitHub=https://github.com/example,github=https://github.com/second',
    ]);
    $logged = instanceLogs();

    expect(InstanceValues::footerLinks())->toBe([
        ['label' => 'Blog', 'href' => 'https://blog.example.com'],
        ['label' => 'Forum', 'href' => 'https://forum.example.com'],
    ])->and(InstanceValues::socialLinks())->toBe([
        ['property' => 'social_link_github', 'href' => 'https://github.com/example'],
        // Two accounts of one kind are two links
        ['property' => 'social_link_github', 'href' => 'https://github.com/second'],
    ])->and($logged)->toHaveCount(2);
});

test('a wrong value is logged once a day, not once for each page', function () {
    config(['instance.footer.links' => 'Shop=http://shop.example.com', 'instance.publisher.name' => 'Example Ltd', 'instance.publisher.url' => 'http://example.com']);
    $logged = instanceLogs();

    foreach (range(1, 3) as $page) {
        $this->get('/login')->assertOk();
        InstanceValues::pageProp();
        InstanceValues::structuredData();
    }
    expect($logged)->toHaveCount(2);

    $this->travel(25)->hours();
    InstanceValues::footerLinks();
    expect($logged)->toHaveCount(3);
});

test('the social links are "key=https://address" entries with a key of the social link properties', function () {
    config(['instance.social_links' => 'github=https://github.com/example, LinkedIn = https://social.example.com/example ,dev_dot_to=https://dev.to/example']);
    $logged = instanceLogs();

    expect(InstanceValues::socialLinks())->toBe([
        ['property' => 'social_link_github', 'href' => 'https://github.com/example'],
        ['property' => 'social_link_linkedin', 'href' => 'https://social.example.com/example'],
        ['property' => 'social_link_dev_dot_to', 'href' => 'https://dev.to/example'],
    ])->and($logged->getArrayCopy())->toBe([]);

    foreach (Constants::SOCIAL_MEDIA_LINK_PROPERTIES as $property) {
        config(['instance.social_links' => substr($property, strlen('social_link_')).'=https://example.com/profile']);

        expect(InstanceValues::socialLinks())->toBe([['property' => $property, 'href' => 'https://example.com/profile']]);
    }
});

test('a wrong social link is left out and logged', function (string $entry) {
    config(['instance.social_links' => 'github=https://github.com/example,'.$entry.',youtube=https://video.example.com/example']);
    $logged = instanceLogs();

    expect(InstanceValues::socialLinks())->toBe([
        ['property' => 'social_link_github', 'href' => 'https://github.com/example'],
        ['property' => 'social_link_youtube', 'href' => 'https://video.example.com/example'],
    ])->and($logged->getArrayCopy())->toBe([
        'warning: Instance values: a social link is left out because it is not "key=https://address" with a known key '.json_encode(['value' => trim($entry)], JSON_UNESCAPED_SLASHES),
    ]);
})->with([
    'an unknown key' => ['myspace=https://myspace.example.com/example'],
    'the whole property name as the key' => ['social_link_github=https://github.com/other'],
    'an empty key' => ['=https://github.com/other'],
    'an http address' => ['gitlab=http://gitlab.example.com/example'],
    'an address without a host' => ['gitlab=https://'],
]);

test('the pages get the values of the instance in the shared prop "instance"', function () {
    config([
        'legal.contact_email' => 'hello@example.com',
        'instance.publisher.name' => 'Example Ltd',
        'instance.footer.note' => 'Made in Ireland.',
        'instance.footer.links' => 'Example Community=https://community.example.com,Blog=https://blog.example.com',
        'instance.social_links' => 'github=https://github.com/example,linkedin=https://social.example.com/example',
    ]);

    $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->where('instance', [
        'contactEmail' => 'hello@example.com',
        'publisher' => 'Example Ltd',
        'footerNote' => 'Made in Ireland.',
        'footerLinks' => [
            ['label' => 'Example Community', 'href' => 'https://community.example.com'],
            ['label' => 'Blog', 'href' => 'https://blog.example.com'],
        ],
        'socialLinks' => [
            ['property' => 'social_link_github', 'href' => 'https://github.com/example'],
            ['property' => 'social_link_linkedin', 'href' => 'https://social.example.com/example'],
        ],
    ]));
});

test('an instance that sets no optional value shares empty values: the pages hide what they feed', function () {
    config([
        'legal.contact_email' => Constants::INSTANCE_PLACEHOLDER_CONTACT_EMAIL,
        'instance.publisher.name' => '', 'instance.footer.note' => '', 'instance.footer.links' => '', 'instance.social_links' => '',
    ]);

    $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->where('instance', [
        'contactEmail' => 'contact@example.com',
        'publisher' => null,
        'footerNote' => null,
        'footerLinks' => [],
        'socialLinks' => [],
    ]));
});

// --- Rule 15: the contact page -----------------------------------------------------------------------------------------------

test('the contact page exists only when the instance gives a contact address', function () {
    config(['legal.contact_email' => 'hello@example.com']);
    $this->get('/contact')->assertOk()->assertInertia(fn ($page) => $page->component('Contact')->where('instance.contactEmail', 'hello@example.com'));

    config(['legal.contact_email' => '']);
    $this->get('/contact')->assertNotFound();

    config(['legal.contact_email' => '   ']);
    $this->get('/contact')->assertNotFound();

    // Only a read: another method answers 404 like any address that matches no route
    config(['legal.contact_email' => 'hello@example.com']);
    $this->post('/contact')->assertNotFound();
});

// --- Rule 17: the structured data ----------------------------------------------------------------------------------------------

test('the structured data names the site, and no publisher when the instance names none', function () {
    config(['app.name' => 'Example Community', 'app.url' => 'https://community.example.com', 'instance.publisher.name' => '', 'instance.publisher.url' => 'https://example.com', 'instance.social_links' => 'github=https://github.com/example']);

    ['data' => $data] = instanceStructuredData((string) $this->get('/')->assertOk()->getContent());

    expect($data)->toBe([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'Example Community',
        'description' => 'Where all communities belong. A place for your Knowledge, your Ideas and Inspiration.',
        'url' => 'https://community.example.com',
    ]);
});

test('the structured data names the publisher of the instance as an organisation, with its address and its social links', function () {
    config([
        'app.name' => 'Example Community', 'app.url' => 'https://community.example.com',
        'instance.publisher.name' => 'Example Ltd', 'instance.publisher.url' => 'https://example.com',
        'instance.social_links' => 'github=https://github.com/example,linkedin=https://social.example.com/example',
    ]);

    ['data' => $data] = instanceStructuredData((string) $this->get('/')->assertOk()->getContent());

    expect($data['publisher'])->toBe([
        '@type' => 'Organization',
        'name' => 'Example Ltd',
        'url' => 'https://example.com',
        'sameAs' => ['https://github.com/example', 'https://social.example.com/example'],
    ])->and(array_keys($data))->toBe(['@context', '@type', 'name', 'description', 'url', 'publisher']);
});

test('the publisher has no address and no "sameAs" when the instance gives none, and an address that is not https is left out', function () {
    config(['instance.publisher.name' => 'Example Ltd', 'instance.publisher.url' => '', 'instance.social_links' => '']);
    expect(InstanceValues::structuredData()['publisher'])->toBe(['@type' => 'Organization', 'name' => 'Example Ltd']);

    config(['instance.publisher.url' => 'http://example.com']);
    $logged = instanceLogs();
    expect(InstanceValues::structuredData()['publisher'])->toBe(['@type' => 'Organization', 'name' => 'Example Ltd'])
        ->and($logged->getArrayCopy())->toBe(['warning: Instance values: INSTANCE_PUBLISHER_URL is left out because it is not an https:// address {"value":"http://example.com"}']);
});

test('a value with markup stays inside the structured data block', function () {
    $name = 'Example </script><script>alert("x")</script> & \'Co\' <!-- Ltd';
    config(['instance.publisher.name' => $name, 'app.name' => 'Example </script> Community']);

    $html = (string) $this->get('/')->assertOk()->getContent();
    ['data' => $data, 'raw' => $raw] = instanceStructuredData($html);

    expect($data['publisher']['name'])->toBe($name)
        ->and($data['name'])->toBe('Example </script> Community')
        ->and($raw)->not->toContain('<')->not->toContain('>')->not->toContain('&')->not->toContain("'")
        ->and($html)->not->toContain('<script>alert("x")</script>');
});

test('a value with bytes that are not UTF-8 never breaks the page', function () {
    config(['instance.publisher.name' => "Caf\xE9 Ltd", 'app.name' => "Caf\xE9"]);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect(instanceStructuredData($html)['data']['publisher']['name'])->toStartWith('Caf');
});

// --- Rules 19 and 21: the seeder and the invitation email ------------------------------------------------------------------------

test('the seeder creates the first user with the configured address', function () {
    config(['instance.seed.admin_email' => 'first@example.com']);

    $this->seed();

    $first = User::query()->where('email', 'first@example.com')->sole();
    expect($first->name)->toBe('Example Admin')
        ->and(User::query()->where('email', 'admin@example.com')->exists())->toBeFalse();
});

test('the seeder falls back to a neutral address when the configuration gives none', function () {
    config(['instance.seed.admin_email' => '']);

    $this->seed();

    expect(User::query()->where('email', 'admin@example.com')->sole()->name)->toBe('Example Admin');
});

test('the invitation email names the configured operator', function () {
    config(['legal.operator' => 'Example Ltd (Ireland)']);
    $c = communityWithMembers();
    $invitation = $c['community']->communityInvitations()->create(['email' => 'grace@example.com', 'role' => KnowiiCommunityMemberRole::Member]);

    $text = html_entity_decode(strip_tags((new CommunityInvitationMail($invitation, $c['admin']))->render()), ENT_QUOTES);

    expect((string) preg_replace('/\s+/', ' ', $text))->toContain('Example Ltd (Ireland) runs Knowii and sends this email for that community.');
});

// --- Rules 12, 18 and 20: what the code and the public files name ----------------------------------------------------------------

test('the shared metadata of the front-end keeps only the address of the repository', function () {
    $metadata = json_decode((string) file_get_contents(base_path('libs/common/src/lib/metadata.json')), true, 8, JSON_THROW_ON_ERROR);

    expect($metadata)->toBe(['repositoryUrl' => 'https://github.com/knowii-oss/knowii']);
});

test('humans.txt and the capture agent name the project, not one instance', function () {
    $humans = (string) file_get_contents(public_path('humans.txt'));

    expect($humans)->toContain("# TEAM\n\n  The Knowii open source project -- https://github.com/knowii-oss/knowii\n  Contributors -- https://github.com/knowii-oss/knowii/graphs/contributors\n")
        ->and(Constants::CAPTURE_USER_AGENT)->toEndWith('Knowii/1.0 (+https://github.com/knowii-oss/knowii)');
});
