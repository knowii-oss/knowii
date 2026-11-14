<?php

namespace App\Contracts\CourseMaterials;

use App\Models\CourseMaterial;
use App\Models\Space;
use App\Models\User;

interface CreatesCourseMaterials
{
    /**
     * Add a file or a link to a course space, to one of its sections or to one of its lessons (#1336).
     *
     * @param  array<mixed>  $input  kind, title, page (optional), and url or file (UploadedFile)
     */
    public function create(User $user, Space $space, array $input): CourseMaterial;
}
