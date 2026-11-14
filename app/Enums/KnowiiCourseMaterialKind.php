<?php

namespace App\Enums;

/**
 * What a course material is (#1336, ADR 0037). WARNING: aligned with courseMaterialKindSchema (course-material.schema.ts)
 */
enum KnowiiCourseMaterialKind: string
{
    // A file stored by Knowii, downloaded through the API
    case File = 'file';
    // A link to a page outside Knowii
    case Link = 'link';
}
