<?php

namespace App\Contracts\InvitationImports;

use App\Models\InvitationImport;
use App\Models\User;

interface ConfirmsInvitationImports
{
    /**
     * Check the valid rows of a preview again, create their invitations with their groups and tags, and queue their mails
     */
    public function confirm(User $user, InvitationImport $import): InvitationImport;
}
