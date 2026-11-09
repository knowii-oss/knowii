<?php

namespace App\Http\Controllers\API;

use App\Constants;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Support\Markdown;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Markdown preview for the editors: the same rendering as the saved content (App\Support\Markdown).
 */
class MarkdownApiController extends Controller
{
    use ApiResponses;

    /**
     * Render Markdown to safe HTML.
     */
    #[BodyParameter('markdown', description: 'Markdown to render', required: true, type: 'string')]
    #[BodyParameter('community_cuid', description: 'Community of the text: @username of its members become links', required: false, type: 'string')]
    final public function preview(Request $request): JsonResponse
    {
        /** @var array{markdown: string|null, community_cuid?: string|null} $validated */
        $validated = Validator::make($request->only(['markdown', 'community_cuid']), [
            'markdown' => ['present', 'nullable', 'string', 'max:'.Constants::MAX_LENGTH_POST_BODY],
            'community_cuid' => ['sometimes', 'nullable', 'string'],
        ])->validate();

        $markdown = $validated['markdown'] ?? '';
        $community = filled($validated['community_cuid'] ?? null) ? Community::where('cuid', $validated['community_cuid'])->first() : null;

        // Mentions only resolve for members of the community
        $html = $community !== null && Gate::check('view', $community)
            ? Markdown::renderWithMentions($markdown, $community)->html
            : Markdown::render($markdown);

        return self::success('Preview', null, ['html' => $html]);
    }
}
