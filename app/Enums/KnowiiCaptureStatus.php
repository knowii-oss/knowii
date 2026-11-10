<?php

namespace App\Enums;

/**
 * Where the capture of a community resource is (ADR 0018). WARNING: aligned with captureStatusSchema
 * (community-resource.schema.ts)
 */
enum KnowiiCaptureStatus: string
{
    case Pending = 'pending';
    case Capturing = 'capturing';
    case Captured = 'captured';
    case Failed = 'failed';
    // The curator kept only the link (#1294): Knowii never opened the page. "Capture again" starts a capture
    case Skipped = 'skipped';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
