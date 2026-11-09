<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Visus\Cuid2\Cuid2;

/**
 * Sets a Cuid2 value on the `cuid` column of the model when it is created,
 * unless a value is already set.
 *
 * Replaces parables/laravel-cuid2, which pins visus/cuid2 to 4.x
 * (incompatible with PHP 8.5).
 */
trait GeneratesCuid
{
    /**
     * Length of the generated Cuid2 values.
     */
    private const int CUID_LENGTH = 24;

    public static function bootGeneratesCuid(): void
    {
        static::creating(static function (Model $model): void {
            if (empty($model->getAttribute('cuid'))) {
                $model->setAttribute('cuid', (new Cuid2(self::CUID_LENGTH))->toString());
            }
        });
    }
}
