<?php

namespace App\Contracts\Spaces;

use App\Models\Space;
use App\Models\User;

interface DeletesSpaces
{
    public function delete(User $user, Space $space): void;
}
