<?php

namespace App\Support;

use App\Constants;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * Offset pagination of the NBB REST API Design Guide (ADR 0011): `limit` and `offset` query parameters, and the
 * pagination metadata of the guide.
 */
final class Pagination
{
    public function __construct(public readonly int $limit, public readonly int $offset) {}

    public static function fromRequest(Request $request, int $defaultLimit = Constants::PAGINATION_DEFAULT_LIMIT): self
    {
        $limit = (int) $request->query('limit', (string) $defaultLimit);
        $offset = (int) $request->query('offset', '0');

        // The guide: 0 means the default, the limit has a maximum, the offset is never negative
        $limit = $limit <= 0 ? $defaultLimit : min($limit, Constants::PAGINATION_MAX_LIMIT);

        return new self($limit, max(0, $offset));
    }

    /**
     * Run the query for the current page.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>|HasMany<TModel, *>  $query
     * @return array{items: Collection<int, TModel>, metadata: array{pagination: array<string, int|null>}}
     */
    public function apply(Builder|HasMany $query): array
    {
        $totalCount = (clone $query)->count();
        /** @var Collection<int, TModel> $items */
        $items = $query->skip($this->offset)->take($this->limit)->get();

        return ['items' => $items, 'metadata' => ['pagination' => $this->metadata($totalCount)]];
    }

    /**
     * @return array<string, int|null>
     */
    public function metadata(int $totalCount): array
    {
        $pageCount = (int) ceil($totalCount / $this->limit);
        $inBounds = $this->offset < $totalCount || ($totalCount === 0 && $this->offset === 0);
        $nextOffset = $this->offset + $this->limit;

        return [
            'limit' => $this->limit,
            'offset' => $this->offset,
            'previousOffset' => $this->offset > 0 ? max(0, $this->offset - $this->limit) : null,
            'nextOffset' => $nextOffset < $totalCount ? $nextOffset : null,
            'currentPage' => $inBounds ? intdiv($this->offset, $this->limit) + 1 : null,
            'pageCount' => $pageCount,
            'totalCount' => $totalCount,
        ];
    }
}
