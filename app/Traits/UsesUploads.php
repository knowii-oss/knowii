<?php

namespace App\Traits;

use App\Models\Upload;
use App\Models\UploadUsage;
use App\Support\UploadReferences;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Items whose Markdown can show uploaded images (posts, comments, #1201). Uses the same item column as the reactions
 * and the mentions (ADR 0014).
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
     * Record the uploads that the body uses, so that `uploads:prune` keeps them. Any upload counts, also one of another
     * community (a quote): its file is public anyway.
     */
    final public function syncUploadUsages(string $body): void
    {
        $this->uploadUsages()->delete();

        $cuids = UploadReferences::cuidsIn($body);
        if ($cuids === []) {
            return;
        }

        foreach (Upload::query()->whereIn('cuid', $cuids)->pluck('id') as $uploadId) {
            $this->uploadUsages()->create(['upload_id' => $uploadId]);
        }
    }
}
