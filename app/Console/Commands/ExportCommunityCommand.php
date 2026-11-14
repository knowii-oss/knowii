<?php

namespace App\Console\Commands;

use App\Contracts\Communities\ExportsCommunities;
use App\Models\Community;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Exports the data of a community to one zip file, on the request of its owner (#1353, DPA section 9). The file is
 * a copy of personal data: `retention:prune` deletes it after `retention.exports_days`. See
 * documentation/runbooks/community-export.md.
 */
#[Signature('communities:export {slug : The slug of the community} {--with-direct-messages : Include the direct messages (private between their participants)}')]
#[Description('Export the data of a community to a zip file')]
class ExportCommunityCommand extends Command
{
    public function handle(ExportsCommunities $exporter): int
    {
        $community = Community::query()->where('slug', $this->argument('slug'))->first();
        if ($community === null) {
            $this->error('No community has this slug.');

            return self::FAILURE;
        }

        $this->line($exporter->export($community, (bool) $this->option('with-direct-messages')));

        return self::SUCCESS;
    }
}
