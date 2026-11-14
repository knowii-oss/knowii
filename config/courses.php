<?php

/*
 * Course spaces: the supplementary materials of courses and lessons (#1336, ADR 0037).
 */
return [
    // The disk of the material files. It must be private: `local` (storage/app, never served by the web server), or
    // an S3-compatible disk of config/filesystems.php (the files are stored with a private visibility and downloaded
    // through temporary signed addresses). Never `public`.
    'materials_disk' => env('COURSE_MATERIALS_DISK', 'local'),

    // Maximum size of a material file, in megabytes. The upload limits of PHP and of the web server must allow it.
    'materials_max_megabytes' => (int) env('COURSE_MATERIALS_MAX_MB', 50),
];
