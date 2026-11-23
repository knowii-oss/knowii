<?php

use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\Event as EventModel;
use App\Models\Page;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Support\ContentSecurityPolicySources;
use App\Support\VideoEmbed;

/**
 * The Content Security Policy never accepts a whole scheme (#1183): every origin is listed explicitly.
 */

/**
 * @return array<string, list<string>> The directives of the CSP header, with their sources
 */
function cspDirectives(string $header): array
{
    $directives = [];

    foreach (array_filter(array_map('trim', explode(';', $header))) as $directive) {
        $parts = preg_split('/\s+/', $directive) ?: [];
        $name = array_shift($parts);
        $directives[(string) $name] = $parts;
    }

    return $directives;
}

it('sends a Content Security Policy without scheme-wide sources', function () {
    $response = $this->get('/');

    $response->assertOk();
    $header = (string) $response->headers->get('Content-Security-Policy');
    expect($header)->not->toBe('');

    foreach (cspDirectives($header) as $name => $sources) {
        foreach ($sources as $source) {
            expect(preg_match('/^(https?|wss?):$/i', $source))
                ->toBe(0, "The {$name} directive accepts the whole {$source} scheme");
            expect($source)->not->toBe('*', "The {$name} directive accepts any origin");
        }
    }
});

it('limits styles, fonts, images, media and manifests to explicit origins', function () {
    $directives = cspDirectives((string) $this->get('/')->headers->get('Content-Security-Policy'));

    expect($directives['style-src'])->toContain("'self'")
        ->and($directives['style-src'])->not->toContain("'unsafe-inline'", 'https://fonts.googleapis.com')
        ->and(collect($directives['style-src'])->contains(fn (string $source) => str_starts_with($source, "'nonce-")))->toBeTrue()
        ->and($directives['font-src'])->toBe(["'self'", 'data:'])
        ->and($directives['img-src'])->toBe(["'self'", 'data:', 'blob:'])
        ->and($directives['media-src'])->toBe(["'self'"])
        ->and($directives['manifest-src'])->toBe(["'self'"]);
});

it('loads no font from a font service: Knowii serves its font itself (#1341)', function () {
    foreach (['/', '/login', '/privacy-policy'] as $path) {
        $this->get($path)
            ->assertOk()
            ->assertDontSee('fonts.googleapis.com')
            ->assertDontSee('fonts.gstatic.com');
    }

    $directives = cspDirectives((string) $this->get('/')->headers->get('Content-Security-Policy'));
    expect(implode(' ', [...$directives['font-src'], ...$directives['style-src']]))->not->toContain('google', 'gstatic');
});

it('allows WebSockets to the Reverb host only', function () {
    $directives = cspDirectives((string) $this->get('/')->headers->get('Content-Security-Policy'));

    $expectedReverb = ContentSecurityPolicySources::reverbOrigin(env('REVERB_HOST'), env('REVERB_PORT'), env('REVERB_SCHEME'));

    expect($directives['connect-src'])->toContain("'self'")
        ->and($directives['connect-src'])->not->toContain('ws:', 'wss:');

    if ($expectedReverb !== null) {
        expect($directives['connect-src'])->toContain($expectedReverb);
    }
});

it('sends on the page of an event with a replay the same frame-src as on a lesson, with the two player hosts only (#1516)', function () {
    $c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $c['community']->id]);
    $events = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Members]);
    $course = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Course, 'visibility' => KnowiiSpaceVisibility::Members]);
    $event = EventModel::factory()->inSpace($events)->hostedBy($c['member'])->past()->create(['replay_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);
    $lesson = Page::factory()->inSpace($course)->create(['author_id' => $c['owner']->id, 'video_url' => 'https://vimeo.com/76979871', 'position' => 1]);
    $this->actingAs($c['member']);
    $slugs = ['communitySlug' => $c['community']->slug];

    $eventPage = $this->get(route('events.show', [...$slugs, 'spaceSlug' => $events->slug, 'eventSlug' => $event->slug]))->assertOk();
    $lessonPage = $this->get(route('pages.show', [...$slugs, 'spaceSlug' => $course->slug, 'pageSlug' => $lesson->slug]))->assertOk();
    $frames = fn ($response) => cspDirectives((string) $response->headers->get('Content-Security-Policy'))['frame-src'] ?? [];

    expect($frames($eventPage))->toBe($frames($lessonPage))
        ->and($frames($eventPage))->toContain(...VideoEmbed::PLAYER_HOSTS)
        ->and(array_diff($frames($eventPage), $frames($this->get('/'))))->toBe([])
        ->and($frames($eventPage))->not->toContain('https://www.youtube.com', 'https://vimeo.com');
});
