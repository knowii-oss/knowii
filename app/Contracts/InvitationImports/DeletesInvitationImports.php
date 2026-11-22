<?php

namespace App\Contracts\InvitationImports;

use App\Models\InvitationImport;
use App\Models\User;

interface DeletesInvitationImports
{
    /**
     * Discard a preview, or delete the record of a sent import early: its invitations stay
     */
    public function delete(User $user, InvitationImport $import): void;
}
