<?php

namespace App\Http\Controllers\API;

use App\Contracts\Uploads\UploadsImages;
use App\Http\Controllers\Controller;
use App\Http\Resources\UploadResource;
use App\Models\Community;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Image uploads for posts and comments (#1178).
 */
class UploadApiController extends Controller
{
    use ApiResponses;

    /**
     * Upload an image (multipart/form-data). Returns its URL, size and alt text, to insert as ![alt](url).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('file', description: 'JPEG, PNG, WebP or GIF, up to 10 MB and 40 megapixels. Re-encoded (metadata removed) and scaled down to 2,000 pixels on the longest side', required: true, type: 'file')]
    #[BodyParameter('alt', description: 'Text alternative for screen readers (required, up to 500 characters)', required: true, type: 'string', example: 'The Knowii logo on a dark background')]
    final public function store(Request $request, Community $community): JsonResponse
    {
        $upload = app(UploadsImages::class)->upload($request->user() ?? throw new AuthenticationException, $community, [
            'file' => $request->file('file'),
            'alt' => $request->input('alt'),
        ]);

        return self::created(new UploadResource($upload), 'Image uploaded');
    }
}
