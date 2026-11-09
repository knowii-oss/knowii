<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Positions of ordered items (0-based, gaps allowed). See the data rules in documentation/conventions/rules.md.
 */
final class Positions
{
    /**
     * The position after the last item of the query (0 when it is empty).
     *
     * @param  HasMany<*, *>  $query
     */
    public static function next(HasMany $query): int
    {
        $max = $query->max('position');

        return is_numeric($max) ? ((int) $max) + 1 : 0;
    }
}
