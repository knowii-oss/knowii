<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\DeletesCommunities;
use App\Models\Community;

class DeleteCommunity implements DeletesCommunities
{
    /**
     * Delete the given community.
     */
    public function delete(Community $community): void
    {
        $community->purge();
    }
}
