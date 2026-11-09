<?php

namespace App\Enums;

/**
 * Who captured the content of a community resource (ADR 0018): the server job or the browser extension.
 * WARNING: aligned with captureSourceSchema (resource-capture.schema.ts)
 */
enum KnowiiCaptureSource: string
{
    case Server = 'server';
    case Extension = 'extension';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
