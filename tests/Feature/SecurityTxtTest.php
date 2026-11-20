<?php

use Illuminate\Support\Carbon;

/*
 * security.txt (#1559, RFC 9116): built from config/security_txt.php, so that each instance names its own contact.
 */

test('security.txt names the contacts of the instance, in their order, with the required fields', function () {
    config([
        'security_txt.contacts' => ['https://example.com/report', 'mailto:security@example.com'],
        'security_txt.expires' => '2027-06-30T00:00:00Z',
    ]);

    $response = $this->get('/.well-known/security.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect((string) $response->getContent())->toBe(implode("\n", [
        'Contact: https://example.com/report',
        'Contact: mailto:security@example.com',
        'Expires: 2027-06-30T00:00:00Z',
        'Preferred-Languages: en',
        'Canonical: '.url('/.well-known/security.txt'),
        'Policy: '.route('security.show'),
    ])."\n");
});

test('security.txt does not exist on an instance without a contact', function () {
    config(['security_txt.contacts' => []]);

    $this->get('/.well-known/security.txt')->assertNotFound();
    $this->get('/security.txt')->assertNotFound();
});

test('security.txt leaves out a contact that is not a mailto:, https:// or tel: address', function () {
    config(['security_txt.contacts' => ['http://example.com/report', 'security@example.com', 'mailto:security@example.com', 'https://example.com/a b']]);

    $body = (string) $this->get('/.well-known/security.txt')->assertOk()->getContent();

    expect($body)->toContain("Contact: mailto:security@example.com\n")
        ->and($body)->not->toContain('http://example.com/report')
        ->and($body)->not->toContain('Contact: security@example.com')
        ->and($body)->not->toContain('a b');

    config(['security_txt.contacts' => ['http://example.com/report']]);
    $this->get('/.well-known/security.txt')->assertNotFound();
});

test('security.txt expires 180 days after the current day when no date is set, or when the date cannot be read', function () {
    Carbon::setTestNow('2026-11-18 15:42:10');
    config(['security_txt.contacts' => ['mailto:security@example.com']]);

    foreach ([null, '', 'not a date'] as $expires) {
        config(['security_txt.expires' => $expires]);

        expect((string) $this->get('/.well-known/security.txt')->getContent())->toContain("Expires: 2027-05-17T00:00:00Z\n");
    }

    Carbon::setTestNow();
});

test('security.txt gives a date with an offset in UTC', function () {
    config(['security_txt.contacts' => ['mailto:security@example.com'], 'security_txt.expires' => '2027-06-30T02:00:00+02:00']);

    expect((string) $this->get('/.well-known/security.txt')->getContent())->toContain("Expires: 2027-06-30T00:00:00Z\n");
});

test('security.txt names the optional pages only when they are https addresses', function () {
    config([
        'security_txt.contacts' => ['mailto:security@example.com'],
        'security_txt.policy' => 'https://example.com/security-policy',
        'security_txt.encryption' => 'https://example.com/key.asc',
        'security_txt.acknowledgments' => 'http://example.com/thanks',
        'security_txt.preferred_languages' => 'en, fr',
    ]);

    $body = (string) $this->get('/.well-known/security.txt')->getContent();

    expect($body)->toContain("Policy: https://example.com/security-policy\n")
        ->and($body)->toContain("Encryption: https://example.com/key.asc\n")
        ->and($body)->toContain("Preferred-Languages: en, fr\n")
        ->and($body)->not->toContain('Acknowledgments:')
        ->and($body)->not->toContain(route('security.show'));
});

test('security.txt names the security page only when the instance has its text (#1653)', function () {
    config(['security_txt.contacts' => ['mailto:security@example.com'], 'security_txt.expires' => '2027-06-30T00:00:00Z']);

    expect((string) $this->get('/.well-known/security.txt')->getContent())->toContain('Policy: '.route('security.show')."\n");

    $this->useLegalFolder(legalTextsWithout('security.md'));

    // The other pages do not count: the line is gone, and the file stays valid
    expect((string) $this->get('/.well-known/security.txt')->assertOk()->getContent())->toBe(implode("\n", [
        'Contact: mailto:security@example.com',
        'Expires: 2027-06-30T00:00:00Z',
        'Preferred-Languages: en',
        'Canonical: '.url('/.well-known/security.txt'),
    ])."\n");
});

test('security.txt names the configured policy page with or without the text of the security page (#1653)', function () {
    config(['security_txt.contacts' => ['mailto:security@example.com'], 'security_txt.policy' => 'https://example.com/security-policy']);

    expect((string) $this->get('/.well-known/security.txt')->getContent())->toContain("Policy: https://example.com/security-policy\n");

    $this->useLegalFolder();

    $body = (string) $this->get('/.well-known/security.txt')->getContent();
    expect($body)->toContain("Policy: https://example.com/security-policy\n")
        ->and(substr_count($body, 'Policy:'))->toBe(1);
});

test('security.txt has no policy line when the configured address is not https and the instance has no security page (#1653)', function () {
    config(['security_txt.contacts' => ['mailto:security@example.com'], 'security_txt.policy' => 'http://example.com/security-policy']);
    $this->useLegalFolder();

    expect((string) $this->get('/.well-known/security.txt')->getContent())->not->toContain('Policy:');
});

test('the old address at the root leads to the one of /.well-known/', function () {
    config(['security_txt.contacts' => ['mailto:security@example.com']]);

    $this->get('/security.txt')->assertStatus(301)->assertRedirect('/.well-known/security.txt');
});

test('the repository holds no static security.txt with the contact of one instance', function () {
    expect(file_exists(public_path('security.txt')))->toBeFalse()
        ->and(file_exists(public_path('.well-known/security.txt')))->toBeFalse();
});
