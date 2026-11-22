<?php

namespace App\Contracts\InvitationImports;

use App\Models\InvitationImport;
use App\Models\User;

interface ResendsInvitationImports
{
    /**
     * Queue the mails of a set of rows again: `rows` is `not_sent` (held and failed) or `not_joined` (sent, one time)
     *
     * @param  array<mixed>  $input
     */
    public function resend(User $user, InvitationImport $import, array $input): InvitationImport;
}
