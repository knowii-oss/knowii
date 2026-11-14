<?php

namespace App\Contracts\CourseMaterials;

use App\Models\CourseMaterial;
use App\Models\User;

interface DeletesCourseMaterials
{
    /**
     * Delete a material and its file (#1336).
     */
    public function delete(User $user, CourseMaterial $material): void;
}
