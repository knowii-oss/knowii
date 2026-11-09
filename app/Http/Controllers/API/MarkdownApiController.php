<?php

namespace App\Http\Controllers\API;

use App\Constants;
use App\Http\Controllers\Controller;
use App\Support\Markdown;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    final public function preview(Request $request): JsonResponse
    {
        /** @var array{markdown: string|null} $validated */
        $validated = Validator::make($request->only(['markdown']), [
            'markdown' => ['present', 'nullable', 'string', 'max:'.Constants::MAX_LENGTH_POST_BODY],
        ])->validate();

        return self::success('Preview', null, ['html' => Markdown::render($validated['markdown'] ?? '')]);
    }
}
