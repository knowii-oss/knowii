<?php

namespace App\Support;

use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\Space;
use App\Models\Upload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the files that deleted content leaves behind (#1265).
 *
 * The database cascades (community → uploads, spaces → community resources), but the disk does not: the files stay, and
 * once their rows are gone, nothing points to them any more (not even `uploads:prune`, #1201). A cleanup is planned
 * before the delete (it reads the rows) and runs after it.
 */
final class ContentFiles
{
    /**
     * @param  list<array{disk: string, path: string}>  $uploads
     * @param  list<int>  $resourceIds
     * @param  list<string>  $communityResourceCuids
     */
    private function __construct(
        private readonly array $uploads,
        private readonly array $resourceIds,
        private readonly array $communityResourceCuids,
    ) {}

    /**
     * The uploads of the community and the resources it shares.
     */
    public static function ofCommunity(Community $community): self
    {
        return new self(
            self::uploads(Upload::query()->where('community_id', $community->id)),
            self::resourceIds(CommunityResource::query()->where('community_id', $community->id)),
            self::communityResourceCuids(CommunityResource::query()->where('community_id', $community->id)),
        );
    }

    /**
     * The resources shared in a resources space. Uploads stay: posts elsewhere can quote them, and `uploads:prune`
     * deletes the unused ones.
     */
    public static function ofSpace(Space $space): self
    {
        return new self(
            [],
            self::resourceIds(CommunityResource::query()->where('space_id', $space->id)),
            self::communityResourceCuids(CommunityResource::query()->where('space_id', $space->id)),
        );
    }

    /**
     * One resource of a resources space (#324): its captured content, and the shared page when no other community
     * uses it.
     */
    public static function ofCommunityResource(CommunityResource $resource): self
    {
        return new self([], [(int) $resource->resource_id], [$resource->cuid]);
    }

    /**
     * Call it after the delete: it runs once the transaction commits (the account deletion wraps the community
     * deletion in one), or at once without a transaction. A rollback keeps the files.
     */
    public function deleteAfterCommit(): void
    {
        DB::afterCommit(fn () => $this->delete());
    }

    private function delete(): void
    {
        foreach ($this->uploads as $upload) {
            Storage::disk($upload['disk'])->delete($upload['path']);
        }

        // The captured content of the community resources (ADR 0018): the database deletes their rows by cascade
        CommunityResourceFiles::delete($this->communityResourceCuids);

        // A resource is shared by communities: it goes with its images only when no community uses it any more
        $unused = Resource::query()
            ->whereIn('id', $this->resourceIds)
            ->whereNotIn('id', CommunityResource::query()->select('resource_id'))
            ->get(['id', 'cuid']);
        foreach ($unused as $resource) {
            ResourceImages::deleteAll($resource);
            $resource->delete();
        }

        Log::info('Content files deleted', ['uploads' => count($this->uploads), 'resources' => $unused->count()]);
    }

    /**
     * @param  Builder<Upload>  $query
     * @return list<array{disk: string, path: string}>
     */
    private static function uploads($query): array
    {
        $files = [];
        foreach ($query->get(['disk', 'path']) as $upload) {
            $files[] = ['disk' => $upload->disk, 'path' => $upload->path];
        }

        return $files;
    }

    /**
     * @param  Builder<CommunityResource>  $query
     * @return list<int>
     */
    private static function resourceIds($query): array
    {
        $ids = [];
        foreach ($query->pluck('resource_id') as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * @param  Builder<CommunityResource>  $query
     * @return list<string>
     */
    private static function communityResourceCuids($query): array
    {
        $cuids = [];
        foreach ($query->pluck('cuid') as $cuid) {
            if (is_string($cuid)) {
                $cuids[] = $cuid;
            }
        }

        return $cuids;
    }
}
