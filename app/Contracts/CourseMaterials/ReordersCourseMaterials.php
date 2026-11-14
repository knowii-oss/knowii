<?php

namespace App\Contracts\CourseMaterials;

use App\Models\Space;
use App\Models\User;

interface ReordersCourseMaterials
{
    /**
     * Put the materials of a course, a section or a lesson in a new order (#1336).
     *
     * @param  array<mixed>  $input  page (optional) and materials: the cuids of every material of that target
     */
    public function reorder(User $user, Space $space, array $input): void;
}
