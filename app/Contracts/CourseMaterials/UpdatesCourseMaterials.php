<?php

namespace App\Contracts\CourseMaterials;

use App\Models\CourseMaterial;
use App\Models\User;

interface UpdatesCourseMaterials
{
    /**
     * Change the title of a material, or the address of a link (#1336).
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, CourseMaterial $material, array $input): CourseMaterial;
}
