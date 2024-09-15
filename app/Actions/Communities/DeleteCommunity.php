<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\DeletesCommunities;
use App\Models\Community;

class DeleteCommunity implements DeletesCommunities
{
  /**
   * Delete the given community.
   */
  final public function delete(Community $community): void
  {
    $community->purge();
  }
}
