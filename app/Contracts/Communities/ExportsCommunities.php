<?php

namespace App\Contracts\Communities;

use App\Models\Community;

interface ExportsCommunities
{
    /**
     * Writes the data of a community to one zip file on the local disk, under `exports/` (#1353): the return of the
     * data that the DPA promises (GDPR art. 28(3)(g)). Direct messages are private between their participants: they
     * go in only when the operator asks for them.
     *
     * @return string the absolute path of the zip file
     */
    public function export(Community $community, bool $withDirectMessages = false): string;
}
