<?php

namespace App\Traits;

use App\Models\Upload;
use App\Models\UploadUsage;
use App\Support\UploadReferences;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Items whose Markdown can show uploaded images (posts, comments, #1201). Uses the same item column as the reactions
 * and the mentions (ADR 0014).
 *
 * @property int $community_id
 */
trait UsesUploads
{
    /**
     * @return HasMany<UploadUsage, covariant $this>
     */
    final public function uploadUsages(): HasMany
    {
        return $this->hasMany(UploadUsage::class, $this->itemForeignKey());
    }

    /**
     * Record the uploads that the body uses, so that `uploads:prune` keeps them. Only an upload of the community of the
     * item counts: an uploaded image lives as long as content of its own community shows it. A text that holds the path
     * of an upload of another community shows the image while it exists, and records nothing.
     */
    final public function syncUploadUsages(string $body): void
    {
        $this->uploadUsages()->delete();

        $cuids = UploadReferences::cuidsIn($body);
        if ($cuids === []) {
            return;
        }

        foreach (Upload::query()->where('community_id', $this->community_id)->whereIn('cuid', $cuids)->pluck('id') as $uploadId) {
            $this->uploadUsages()->create(['upload_id' => $uploadId]);
        }
    }
}
