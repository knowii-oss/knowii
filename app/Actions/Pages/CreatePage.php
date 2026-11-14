<?php

namespace App\Actions\Pages;

use App\Constants;
use App\Contracts\Pages\CreatesPages;
use App\Enums\KnowiiPageKind;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use App\Support\Markdown;
use App\Support\Positions;
use App\Support\VideoEmbed;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Add a page at the end of a page space (#1179), or a lesson or a section at the end of a course space (#1188, ADR
 * 0029): the owner, the admins and the moderators.
 */
class CreatePage implements CreatesPages
{
    final public function create(User $user, Space $space, array $input): Page
    {
        Log::info('Processing request to create a page', ['space' => $space->cuid]);

        Gate::forUser($user)->authorize('managePages', $space);

        /** @var array{kind?: string} $kindInput */
        $kindInput = Validator::make($input, ['kind' => ['sometimes', Rule::enum(KnowiiPageKind::class)]])->validate();
        $kind = KnowiiPageKind::from($kindInput['kind'] ?? KnowiiPageKind::Page->value);

        /** @var array{title: string, body?: string|null, video_url?: string|null} $validated */
        $validated = Validator::make($input, self::rules(true, $kind))->validate();
        self::assertVideoAllowed($space, $kind, $validated['video_url'] ?? null);

        $page = DB::transaction(function () use ($space, $user, $validated, $kind) {
            // A section is a heading: no text (#1188)
            $body = $kind === KnowiiPageKind::Section ? '' : (string) ($validated['body'] ?? '');
            $page = Page::create([
                'community_id' => $space->community_id,
                'space_id' => $space->id,
                'kind' => $kind,
                'author_id' => $user->id,
                'title' => trim($validated['title']),
                'body' => $body,
                // Raw HTML escaped (ADR 0013)
                'body_html' => $body === '' ? '' : Markdown::render($body),
                'video_url' => self::blankToNull($validated['video_url'] ?? null),
                'position' => Positions::next($space->pages()->reorder()),
            ]);
            // Uploaded images stay while the page shows them (#1201)
            $page->syncUploadUsages($body);

            return $page;
        });

        Log::info('Page created', ['page' => $page->cuid]);
        event(SpaceContentChanged::forPage(SpaceContentChanged::PAGE_CREATED, $page, $user->cuid));

        return $page;
    }

    /**
     * WARNING: aligned with pageFormSchema (page.schema.ts)
     *
     * @return array<string, list<string|object>>
     */
    public static function rules(bool $required, KnowiiPageKind $kind = KnowiiPageKind::Page): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'min:'.Constants::MIN_LENGTH_POST_TITLE, 'max:'.Constants::MAX_LENGTH_POST_TITLE],
            // A section has a title only (#1188)
            'body' => $kind === KnowiiPageKind::Section
                ? ['sometimes', 'nullable', 'string', 'max:0']
                : [$presence, 'string', 'min:1', 'max:'.Constants::MAX_LENGTH_POST_BODY],
            // Lessons of a course space (#1188): YouTube or Vimeo only (App\Support\VideoEmbed)
            'video_url' => ['sometimes', 'nullable', 'string', 'max:'.Constants::MAX_LENGTH_SPACE_URL, static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && VideoEmbed::parse($value) === null) {
                    $fail('Use the address of a YouTube or Vimeo video');
                }
            }],
        ];
    }

    /**
     * Only a lesson of a course space has a video (#1188)
     */
    public static function assertVideoAllowed(Space $space, KnowiiPageKind $kind, ?string $videoUrl): void
    {
        if (self::blankToNull($videoUrl) !== null && ($space->type !== KnowiiSpaceType::Course || $kind !== KnowiiPageKind::Page)) {
            throw ValidationException::withMessages(['video_url' => 'Only the lessons of a course space have a video']);
        }
    }

    public static function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
