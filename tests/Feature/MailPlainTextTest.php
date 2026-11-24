<?php

use App\Enums\KnowiiCommunityVisibility;
use App\Mail\CommunityInvitation;
use App\Mail\CommunityOwnershipTransferred;
use App\Mail\ConfirmNewEmailAddress;
use App\Mail\EmailAddressChanged;
use App\Mail\EmailAddressHasAccount;
use App\Mail\FinishSignUp;
use App\Mail\MagicLink;
use App\Mail\MailBudgetAlert;
use App\Mail\SignUpAccountExists;
use App\Models\Community;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use App\Notifications\VerifyEmailAddress;
use App\Support\MailBudgetCheck;
use App\Support\MailText;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Dom\HTMLDocument;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

/*
 * A mail prints a value of a person as one line of plain text (ADR 0069): the name of a person, the name of a community,
 * an address typed in a form. Each mail of the application is sent for real with the array mailer, and the tests read
 * what a mail program gets: the subject, the HTML part and the plain-text part.
 *
 * A new mail class, in any folder of `app/`, needs a case in mailPlainTextCase(): the first test fails without it.
 */

/**
 * What a person can type in a name: line breaks, a heading, a link, an image, HTML, an address between angle brackets,
 * emphasis, code, a list, a quote, a line, a numbered list, a character reference, a control character and a direction
 * mark
 */
const MAIL_PLAIN_TEXT_TYPED = "Sam O'Neil\r\n\r\n# A heading\n\n[a link](https://other.example/k) ![banner](https://other.example/p.png) <a href=\"https://other.example/raw\">raw</a> <https://other.example/auto> **bold** _it_ `code`\n- item\n> quote\n\n---\n1. one & two &copy;\u{0007}\u{202E}";

/**
 * The same text as the mail prints it: one line, each character as the person typed it
 */
const MAIL_PLAIN_TEXT_PRINTED = "Sam O'Neil # A heading [a link](https://other.example/k) ![banner](https://other.example/p.png) <a href=\"https://other.example/raw\">raw</a> <https://other.example/auto> **bold** _it_ `code` - item > quote --- 1. one & two &copy;";

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-24T10:00:00Z'));
    config(['mail.default' => 'array', 'queue.default' => 'sync', 'app.url' => 'https://knowii.test', 'legal.operator' => 'Example Ltd (Ireland)', 'legal.contact_email' => 'hello@example.com']);
    URL::forceRootUrl('https://knowii.test');
    URL::forceScheme('https');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Every mail class of the application that can be sent: the classes of applicationMailClasses() that are not abstract
 *
 * @return list<class-string>
 */
function mailPlainTextClasses(): array
{
    return array_values(array_filter(applicationMailClasses(), static fn (string $class): bool => ! (new ReflectionClass($class))->isAbstract()));
}

/**
 * One mail of the class, with the typed text in each value of a person. `send` sends it now. `values` is the number of
 * paragraphs that print a value of a person. `subject` is the whole subject. `anchors` are the links of the body, in
 * their order: the text, the address, and true for a button
 *
 * @return array{send: Closure(): void, values: int, subject: string, anchors: list<array{0: string, 1: string, 2: bool}>}
 */
function mailPlainTextCase(string $class): array
{
    $person = static fn (): User => User::factory()->withUserProfile()->create(['name' => MAIL_PLAIN_TEXT_TYPED]);
    $to = static fn (object $mail): Closure => static fn () => Mail::to('reader@example.com')->sendNow($mail);

    switch ($class) {
        case CommunityInvitation::class:
            $inviter = $person();
            $community = Community::factory()->create(['owner_id' => $inviter->id, 'name' => MAIL_PLAIN_TEXT_TYPED, 'visibility' => KnowiiCommunityVisibility::Private]);
            $invitation = $community->communityInvitations()->create(['email' => 'reader@example.com', 'role' => 'member']);

            return [
                'send' => $to(new CommunityInvitation($invitation, $inviter)),
                // The first sentence (the community) and the sentence about the inviter
                'values' => 2,
                'subject' => 'You are invited to '.MAIL_PLAIN_TEXT_PRINTED.' on Knowii',
                'anchors' => [
                    ['Create an account', 'https://knowii.test/register', true],
                    ['Accept the invitation', URL::temporarySignedRoute('community-invitations.accept', $invitation->expiresAt(), ['invitation' => $invitation]), true],
                ],
            ];
        case CommunityOwnershipTransferred::class:
            $former = $person();
            $newOwner = $person();
            $community = Community::factory()->create(['owner_id' => $newOwner->id, 'name' => MAIL_PLAIN_TEXT_TYPED, 'visibility' => KnowiiCommunityVisibility::Private]);

            return [
                'send' => $to(new CommunityOwnershipTransferred($community, $former)),
                'values' => 2,
                'subject' => 'You are now the owner of '.MAIL_PLAIN_TEXT_PRINTED.' on Knowii',
                'anchors' => [['Open the settings of the community', 'https://knowii.test/community/'.$community->slug.'/settings', true]],
            ];
        case ConfirmNewEmailAddress::class:
            $url = 'https://knowii.test/email/change/1/abc?expires=1795600000&signature=0123abcd';

            return ['send' => $to(new ConfirmNewEmailAddress($url, 60)), 'values' => 0, 'subject' => 'Confirm your new email address', 'anchors' => [['Confirm your new email address', $url, true]]];
        case EmailAddressChanged::class:
            // The new address, masked: what a person typed in the form of the profile
            return ['send' => $to(new EmailAddressChanged(MAIL_PLAIN_TEXT_TYPED)), 'values' => 1, 'subject' => 'The email address of your account on Knowii changed', 'anchors' => []];
        case EmailAddressHasAccount::class:
            return ['send' => $to(new EmailAddressHasAccount), 'values' => 0, 'subject' => 'This address already has an account', 'anchors' => []];
        case FinishSignUp::class:
            $token = str_repeat('a1', 32);

            return ['send' => $to(new FinishSignUp($token, 60)), 'values' => 0, 'subject' => 'Finish your sign-up to Knowii', 'anchors' => [['Finish my sign-up', 'https://knowii.test/register/link/'.$token, true]]];
        case MagicLink::class:
            $token = str_repeat('b2', 32);

            return ['send' => $to(new MagicLink($token)), 'values' => 0, 'subject' => 'Your sign-in link for Knowii', 'anchors' => [['Sign in', 'https://knowii.test/login/link/'.$token, true]]];
        case MailBudgetAlert::class:
            return [
                'send' => $to(new MailBudgetAlert(CarbonImmutable::parse('2026-11-01', 'UTC'), 2400, 3000, 80, MailBudgetCheck::SOURCE_PROVIDER, 90, null)),
                'values' => 0,
                'subject' => '80% of the email quota of November 2026 is used',
                'anchors' => [],
            ];
        case SignUpAccountExists::class:
            return [
                'send' => $to(new SignUpAccountExists),
                'values' => 0,
                'subject' => 'You already have an account on Knowii',
                'anchors' => [['Sign in', 'https://knowii.test/login', true], ['Reset my password', 'https://knowii.test/forgot-password', false]],
            ];
        case ResetPasswordLink::class:
            $user = $person();
            $token = str_repeat('c3', 32);
            $url = 'https://knowii.test/reset-password/'.$token.'?email='.urlencode($user->email);

            return [
                'send' => static fn () => $user->notifyNow(new ResetPasswordLink($token)),
                'values' => 0,
                'subject' => 'Reset your password',
                // The button, then the same address as a link under the mail
                'anchors' => [['Reset Password', $url, true], [$url, $url, false]],
            ];
        case VerifyEmailAddress::class:
            $user = $person();
            $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->getKey(), 'hash' => sha1($user->email)]);

            return [
                'send' => static fn () => $user->notifyNow(new VerifyEmailAddress),
                'values' => 0,
                'subject' => 'Verify your email address',
                'anchors' => [['Verify Email Address', $url, true], [$url, $url, false]],
            ];
        default:
            throw new LogicException("The mail class {$class} has no case in mailPlainTextCase(): add one, with each value of a person set to MAIL_PLAIN_TEXT_TYPED");
    }
}

/**
 * The last mail that the array mailer got
 */
function mailPlainTextLastMail(): Email
{
    $transport = Mail::mailer('array')->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    $message = $transport instanceof ArrayTransport ? $transport->messages()->last()?->getOriginalMessage() : null;
    expect($message)->toBeInstanceOf(Email::class);

    /** @var Email $message */
    return $message;
}

function mailPlainTextOneSpace(string $text): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

test('every mail class of the application has a case in this file', function () {
    $classes = mailPlainTextClasses();

    expect($classes)->toContain(CommunityInvitation::class, VerifyEmailAddress::class)
        ->and(count($classes))->toBeGreaterThanOrEqual(11);

    foreach ($classes as $class) {
        expect(fn () => mailPlainTextCase($class))->not->toThrow(LogicException::class);
    }
});

test('a mail prints a value of a person as one line of plain text, and its links are its own', function (string $class) {
    $case = mailPlainTextCase($class);
    ($case['send'])();

    $mail = mailPlainTextLastMail();
    $html = (string) $mail->getHtmlBody();
    $text = (string) $mail->getTextBody();

    // The subject, and every header: one line, no control character
    expect($mail->getSubject())->toBe($case['subject']);
    foreach ($mail->getHeaders()->all() as $header) {
        expect($header->getBodyAsString())->not->toMatch('/[\r\n]|\p{Cc}|[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', 'the header '.$header->getName());
    }

    // The HTML part, read as a mail program reads it
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    $anchors = [];
    foreach ($document->querySelectorAll('a') as $anchor) {
        $anchors[] = [mailPlainTextOneSpace($anchor->textContent), (string) $anchor->getAttribute('href'), str_contains((string) $anchor->getAttribute('class'), 'button')];
    }
    // The first link of every mail is the name of the application, in the header
    expect(array_shift($anchors))->toBe(['Knowii', 'https://knowii.test', false])
        ->and($anchors)->toBe($case['anchors'])
        ->and($document->querySelectorAll('img, script, iframe, form')->length)->toBe(0);
    // No attribute of any element holds a part of the typed text: no address, no title, no alternative text
    foreach ($document->querySelectorAll('*') as $element) {
        foreach ($element->attributes as $attribute) {
            expect($attribute->value)->not->toContain('other.example', $element->tagName.' '.$attribute->name)
                ->and($attribute->value)->not->toContain('Sam O', $element->tagName.' '.$attribute->name);
        }
    }

    // The typed text made no heading, no emphasis, no code, no list and no quote: it is the plain text of a paragraph
    foreach ($document->querySelectorAll('h1, h2, h3, h4, h5, h6, em, strong, code, pre, li, blockquote, th, td.panel-item') as $element) {
        expect($element->textContent)->not->toContain('A heading')
            ->and($element->textContent)->not->toContain('bold')
            ->and($element->textContent)->not->toContain('quote');
    }
    $paragraphs = [];
    foreach ($document->querySelectorAll('p') as $paragraph) {
        if (str_contains($paragraph->textContent, "Sam O'Neil")) {
            $paragraphs[] = $paragraph;
        }
    }
    expect($paragraphs)->toHaveCount($case['values']);
    foreach ($paragraphs as $paragraph) {
        expect($paragraph->childElementCount)->toBe(0)
            ->and(mailPlainTextOneSpace($paragraph->textContent))->toContain(MAIL_PLAIN_TEXT_PRINTED);
    }
    // No other place of the mail holds a part of the typed text
    expect(substr_count($document->body?->textContent ?? '', 'A heading'))->toBe(substr_count(implode(' ', array_map(static fn ($paragraph) => $paragraph->textContent, $paragraphs)), 'A heading'));

    // The plain-text part: a mail program shows it as it is, and makes at most a bare address active. The typed text is
    // on one line, with its sentence. This typed text starts with "Sam O'Neil", so its "#" starts no line here. A sentence
    // can start with a value (":name invited you"), and then a typed "#" starts a line of this part: a mail program
    // reads no Markdown in a plain-text part, so it shows a "#"
    $lines = array_values(array_filter(explode("\n", $text), static fn (string $line): bool => str_contains($line, "Sam O'Neil")));
    expect($lines)->toHaveCount($case['values']);
    foreach ($lines as $line) {
        expect($line)->toContain(MAIL_PLAIN_TEXT_PRINTED);
    }
    expect(substr_count($text, 'A heading'))->toBe(substr_count(implode(' ', $lines), 'A heading'))
        ->and($text)->not->toMatch('/^\s*(#+ A heading|\[a link|!\[banner|<a |- item|> quote)/m')
        ->and($text)->not->toContain('&#')
        ->and($text)->not->toContain('\\');

    // Each button is in the plain-text part too, with the same address
    foreach ($case['anchors'] as [$label, $address, $isButton]) {
        if ($isButton) {
            expect($text)->toContain($label.': '.$address);
        }
    }
})->with(fn () => array_combine(array_map(static fn (string $class): string => class_basename($class), mailPlainTextClasses()), array_map(static fn (string $class): array => [$class], mailPlainTextClasses())));

test('the signed links of the mails are valid links of the application', function () {
    $case = mailPlainTextCase(CommunityInvitation::class);
    ($case['send'])();
    $document = HTMLDocument::createFromString((string) mailPlainTextLastMail()->getHtmlBody(), LIBXML_NOERROR);
    $accept = (string) $document->querySelectorAll('a.button')->item(1)?->getAttribute('href');

    $case = mailPlainTextCase(VerifyEmailAddress::class);
    ($case['send'])();
    $document = HTMLDocument::createFromString((string) mailPlainTextLastMail()->getHtmlBody(), LIBXML_NOERROR);
    $verify = (string) $document->querySelector('a.button')?->getAttribute('href');

    expect($accept)->toContain('/community-invitations/')
        ->and(URL::hasValidSignature(Request::create($accept)))->toBeTrue()
        ->and($verify)->toContain('/email/verify/')
        ->and(URL::hasValidSignature(Request::create($verify)))->toBeTrue();
});

test('a normal name shows in a mail as the person wrote it', function (string $name) {
    $inviter = User::factory()->withUserProfile()->create(['name' => $name]);
    $community = Community::factory()->create(['owner_id' => $inviter->id, 'name' => "Zoé's Notes @ Liège"]);
    $invitation = $community->communityInvitations()->create(['email' => 'reader@example.com', 'role' => 'member']);

    Mail::to('reader@example.com')->sendNow(new CommunityInvitation($invitation, $inviter));

    $mail = mailPlainTextLastMail();
    $document = HTMLDocument::createFromString((string) $mail->getHtmlBody(), LIBXML_NOERROR);
    $shown = $document->body?->textContent ?? '';

    expect($shown)->toContain($name.' invited you to the Zoé\'s Notes @ Liège community.')
        ->and((string) $mail->getTextBody())->toContain($name.' invited you to the Zoé\'s Notes @ Liège community.')
        ->and($mail->getSubject())->toBe("You are invited to Zoé's Notes @ Liège on Knowii")
        // No backslash and no character reference shows
        ->and($shown)->not->toContain('&#')
        ->and($shown)->not->toContain('&amp;')
        ->and((string) $mail->getTextBody())->not->toContain('&#')
        ->and((string) $mail->getTextBody())->not->toContain('&amp;')
        ->and(substr_count($shown, '\\'))->toBe(substr_count($name, '\\'));
})->with([
    'an apostrophe' => ["Sam O'Neil"],
    'an ampersand' => ['Tom & Jerry'],
    'brackets' => ['Ada [Team] (EU) {x}'],
    'underscores' => ['ada_lovelace_ _x_'],
    'stars, a number sign and a pipe' => ['*Ada* #1 | a|b'],
    'a quote and a less-than sign' => ['Ada "The <3" Lovelace'],
    'a dot after a number' => ['1984. Ada'],
    'a backslash' => ['Ada \\ Lovelace'],
    'letters with accents' => ['Zoé Lefèvre-Müller'],
    'Arabic' => ['محمد علي'],
    'Hebrew' => ['דוד בן'],
    'Chinese' => ['王小明'],
    'an emoji with a zero width joiner' => ["Ada \u{1F469}\u{200D}\u{1F4BB}"],
]);

test('the text of a mail for a value of a person is one line with no active character', function () {
    expect(MailText::plain("a\n# b [c](d) <e> & `f` *g* _h_")->toHtml())->toBe('a &#35; b &#91;c&#93;&#40;d&#41; &#60;e&#62; &#38; &#96;f&#96; &#42;g&#42; &#95;h&#95;')
        ->and(MailText::plain(null)->toHtml())->toBe('')
        ->and(MailText::plain(" \u{202E}\u{0000} ")->toHtml())->toBe('')
        ->and(MailText::plain('Zoé 王小明')->toHtml())->toBe('Zoé 王小明')
        ->and(MailText::subject("Notes\r\nof Ada\u{0007}\u{202E}"))->toBe('Notes of Ada')
        ->and(MailText::subject(null))->toBe('');
});

/**
 * An address with both quotes in it: in an attribute that a quote can close, it would add attributes of its own
 */
const MAIL_PLAIN_TEXT_QUOTED_ADDRESS = "https://knowii.test/a?q=\"x\"onmouseover=\"alert(1)\"'y'data-x='z'&b=1";

test('a value in an attribute of a mail keeps its quotes inside the attribute', function (string $view, array $data, int $links) {
    // The data of the view: each address that it puts in an attribute is MAIL_PLAIN_TEXT_QUOTED_ADDRESS
    $data = array_map(static fn (mixed $value): mixed => $value === 'QUOTED' ? MAIL_PLAIN_TEXT_QUOTED_ADDRESS : $value, $data);
    $markdown = app(Markdown::class);

    $html = (string) $markdown->render($view, $data);
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    $found = 0;
    foreach ($document->querySelectorAll('a') as $anchor) {
        $names = [];
        foreach ($anchor->attributes as $attribute) {
            $names[] = $attribute->name;
        }
        expect(array_diff($names, ['href', 'class', 'target', 'rel', 'style']))->toBe([], 'the attributes of a link of '.$view);
        // A link of Markdown writes a quote of its address as "%22"
        if (rawurldecode((string) $anchor->getAttribute('href')) === MAIL_PLAIN_TEXT_QUOTED_ADDRESS) {
            $found++;
        }
    }
    expect($found)->toBe($links)
        ->and(substr_count((string) $markdown->renderText($view, $data), MAIL_PLAIN_TEXT_QUOTED_ADDRESS))->toBe($links);
})->with([
    'the invitation' => ['emails.community-invitation', ['communityName' => 'Notes', 'inviterName' => 'Ada', 'acceptUrl' => 'QUOTED', 'expiresAt' => CarbonImmutable::parse('2026-12-01'), 'operator' => 'Example Ltd', 'deletedAfter' => '30 days', 'privacyPolicyUrl' => null], 1],
    'the new owner' => ['emails.community-ownership-transferred', ['communityName' => 'Notes', 'formerOwnerName' => 'Ada', 'settingsUrl' => 'QUOTED'], 1],
    'the new address to confirm' => ['emails.confirm-new-email-address', ['url' => 'QUOTED', 'minutes' => 60], 1],
    'the end of the sign-up' => ['emails.finish-sign-up', ['url' => 'QUOTED', 'minutes' => 60], 1],
    'the sign-in link' => ['emails.magic-link', ['url' => 'QUOTED', 'minutes' => 15], 1],
    'the account that exists' => ['emails.sign-up-account-exists', ['signInUrl' => 'QUOTED', 'resetUrl' => 'QUOTED'], 2],
    'the footer of a notification mail' => ['emails.partials.notification-footer', ['stopUrl' => 'QUOTED', 'settingsUrl' => 'QUOTED', 'operatorLine' => 'Example Ltd'], 2],
]);
