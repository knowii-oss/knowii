<?php

namespace App\Actions\Resources;

use App\Constants;
use App\Contracts\Resources\CreatesCommunityResources;
use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiResourceType;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Jobs\CaptureCommunityResource;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\Space;
use App\Models\User;
use App\Support\SafeUrl;
use App\Traits\UrlCleanup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Add a link to a resources space (ADR 0015, ADR 0018). The request is fast: it checks the input, creates the
 * community resource with the capture status "pending" and a name from the member or from the URL, and queues the
 * capture. It makes no HTTP request.
 *
 * With "capture" false (#1294), Knowii never opens the page: no capture is queued, the status is "skipped", and the
 * address is not resolved either (pages behind a sign-in, sites that block bots). "Capture again" starts a capture
 * later, with the checks of a normal capture.
 */
class CreateCommunityResource implements CreatesCommunityResources
{
    use UrlCleanup;

    /**
     * @param  array<mixed>  $input
     */
    final public function create(User $user, Space $space, array $input): CommunityResource
    {
        Log::info('Processing request to add a resource', ['space' => $space->cuid]);

        Gate::forUser($user)->authorize('contribute', $space);

        if ($space->type !== KnowiiSpaceType::Resources) {
            throw new BusinessException('Resources can only be added to resources spaces');
        }

        /** @var array{name?: string|null, description?: string|null, url: string, level: string, type?: string|null, capture?: bool} $input */
        $input = Validator::make($input, [
            // Optional: the title of the page replaces it after the capture (#1062)
            'name' => ['nullable', 'string', 'min:'.Constants::MIN_LENGTH_COMMUNITY_RESOURCE_NAME, 'max:'.Constants::MAX_LENGTH_COMMUNITY_RESOURCE_NAME, 'regex:'.Constants::ALLOWED_COMMUNITY_RESOURCE_NAME_CHARACTERS_REGEX],
            'description' => ['nullable', 'string', 'max:'.Constants::MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION],
            'url' => ['required', 'string', 'url:http,https', 'max:2048'],
            'level' => ['required', Rule::enum(KnowiiResourceLevel::class)],
            // Optional: the capture detects it (#1246); a type from the member stays
            'type' => ['nullable', Rule::enum(KnowiiResourceType::class)],
            // false: keep only the link, Knowii never opens the page (#1294)
            'capture' => ['sometimes', 'boolean'],
        ])->validate();

        $capture = (bool) ($input['capture'] ?? true);
        $url = $this->cleanupUrl($input['url']);
        if ($capture) {
            // A private or local address is refused at once (#1231); the job checks every redirect again
            SafeUrl::assertPublic($url);
        }

        if ($space->containsResourceWithUrl($url)) {
            throw new BusinessException('This resource is already in this space');
        }

        $memberName = blank($input['name'] ?? null) ? null : trim((string) $input['name']);
        $urlTitle = self::titleFromUrl($url);
        $name = $memberName ?? self::nameFromUrl($url);

        $communityResource = DB::transaction(static function () use ($user, $space, $url, $urlTitle, $name, $input, $capture) {
            // One resource per URL, shared by the communities: its metadata comes from the capture
            $resource = Resource::query()->where('url', $url)->first() ?? Resource::create([
                'name' => $urlTitle,
                'url' => $url,
                'keywords' => [],
                // Until the capture detects it
                'type' => KnowiiResourceType::Other,
            ]);
            $chosenType = blank($input['type'] ?? null) ? null : KnowiiResourceType::from((string) $input['type']);

            return CommunityResource::create([
                'slug' => $resource->slug,
                'name' => $name,
                'description' => $input['description'] ?? null,
                'resource_id' => $resource->id,
                'community_id' => $space->community_id,
                'space_id' => $space->id,
                'type' => $chosenType ?? $resource->type,
                'type_chosen' => $chosenType !== null,
                'level' => KnowiiResourceLevel::from($input['level']),
                'capture_status' => $capture ? KnowiiCaptureStatus::Pending : KnowiiCaptureStatus::Skipped,
                // A profile, not a user: the curation stays when the account goes (ADR 0024)
                'curator_id' => $user->profile->id,
                'is_featured' => false,
            ]);
        });

        if (! $capture) {
            Log::info('Resource added without capture', ['resource' => $communityResource->cuid]);

            return $communityResource;
        }

        // Without a name from the member, the capture gives the resource the title of the page (#1062)
        CaptureCommunityResource::dispatch($communityResource, $memberName === null)->afterCommit();

        Log::info('Resource added, capture queued', ['resource' => $communityResource->cuid]);

        return $communityResource;
    }

    /**
     * The name that a resource gets from its URL when the member gives none (shown until a capture gives the title)
     */
    public static function nameFromUrl(string $url): string
    {
        return Str::limit(self::titleFromUrl($url), Constants::MAX_LENGTH_COMMUNITY_RESOURCE_NAME - 1, '…');
    }

    /**
     * "https://example.com/blog/smart-notes_guide/" gives "Smart Notes Guide"; without a path, the host
     */
    public static function titleFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = explode('/', $path);
        $title = trim(ucwords(str_replace(['-', '_'], ' ', (string) end($segments))));

        if ($title !== '') {
            return $title;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);

        return preg_replace('/^www\./', '', $host) ?: $url;
    }
}
