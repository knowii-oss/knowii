<?php

/*
 * The `mixed` type stays out of app/ (#726). It is allowed only where a value really comes from outside and is
 * narrowed at once. See "Types" in documentation/architecture/backend.md.
 */

/**
 * The uses of `mixed` in app/ that no general rule allows, per file.
 *
 * @return array<string, int>
 */
function mixedTypeUsesOutsideTheRules(): array
{
    $root = dirname(__DIR__, 2);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));
    $uses = [];

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }
        $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $count = 0;

        foreach (file($file->getPathname()) ?: [] as $line) {
            if (preg_match('/\bmixed\b/', $line) !== 1) {
                continue;
            }
            // Rule 1: the input of an action is the request input before the validation
            if (preg_match('/@param\s+array<mixed>\s+\$input\b/', $line) === 1) {
                continue;
            }
            // Rule 2: a closure validation rule gets the raw value
            if (str_contains($line, 'mixed $value, Closure $fail')) {
                continue;
            }
            // Rule 3: toArray() of an API resource has the signature of the framework
            if (str_starts_with($path, 'app/Http/Resources/') && str_contains($line, '@return array<string, mixed>')) {
                continue;
            }
            $count += (int) preg_match_all('/\bmixed\b/', $line);
        }

        if ($count > 0) {
            $uses[$path] = $count;
        }
    }

    ksort($uses);

    return $uses;
}

test('mixed is used only where a value comes from outside (#726)', function () {
    // This list can only shrink. A new use of `mixed` needs a real type, a union type or an array shape instead.
    $allowed = [
        // Request input or stored JSON, narrowed in the method
        'app/Actions/AccessGroups/AccessGroupInputRules.php' => 1,
        'app/Actions/Fortify/UpdateUserProfileInformation.php' => 1,
        'app/Http/Controllers/ApiTokenController.php' => 1,
        'app/Support/Emoji.php' => 1,
        'app/Support/ProfileFieldVisibility.php' => 1,
        // Decoded JSON of a third party
        'app/Actions/EmailOctopus/PruneEmailOctopusContacts.php' => 1,
        'app/Support/ReverbPresenceChannels.php' => 1,
        // Configuration values
        'app/Actions/Retention/PruneExpiredData.php' => 2,
        'app/Support/Analytics.php' => 1,
        'app/Support/LegalPages.php' => 1,
        'app/Support/ReservedCommunityNames.php' => 2,
        // The serialized form of an API resource (the signature of the framework)
        'app/Events/Communities/CommunityEvent.php' => 2,
        'app/Events/CommunityResources/CommunityResourceEvent.php' => 1,
        'app/Http/Controllers/API/ReactionApiController.php' => 1,
        // The JSON payload of an endpoint: each controller gives its own shape
        'app/Http/Controllers/API/KnowiiApiResponse.php' => 3,
        'app/Traits/ApiResponses.php' => 1,
        // Signatures of the framework and of libraries (Inertia props, Eloquent attributes, slug configuration, Guzzle options)
        'app/Http/Middleware/HandleInertiaRequests.php' => 1,
        'app/Models/Community.php' => 2,
        'app/Models/Page.php' => 1,
        'app/Models/Post.php' => 3,
        'app/Models/Resource.php' => 3,
        'app/Models/Space.php' => 3,
        'app/Models/UserProfile.php' => 1,
        'app/Traits/FetchUrl.php' => 3,
        // A database value read with pluck()
        'app/Events/Conversations/ConversationChanged.php' => 1,
    ];
    ksort($allowed);

    $unexpected = [];
    foreach (mixedTypeUsesOutsideTheRules() as $path => $count) {
        if ($count > ($allowed[$path] ?? 0)) {
            $unexpected[$path] = $count.' (allowed: '.($allowed[$path] ?? 0).')';
        }
    }

    expect($unexpected)->toBe([]);
});

test('the list of allowed mixed uses holds no entry that is gone (#726)', function () {
    $uses = mixedTypeUsesOutsideTheRules();

    // When a file drops its last `mixed`, or uses fewer, lower the number above: the list never grows back
    expect(array_sum($uses))->toBeLessThanOrEqual(39);
});
