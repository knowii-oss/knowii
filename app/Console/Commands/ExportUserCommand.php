<?php

namespace App\Console\Commands;

use App\Contracts\Users\ExportsUsers;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Exports the data of a member to one zip file, on the request of the member (#1343, privacy policy section 8). The
 * file is a copy of personal data: `retention:prune` deletes it after `retention.exports_days`. See
 * documentation/runbooks/member-data-export.md.
 */
#[Signature('users:export {email : The email address of the account}')]
#[Description('Export the data of a member to a zip file')]
class ExportUserCommand extends Command
{
    public function handle(ExportsUsers $exporter): int
    {
        // The stored address is in lower case: the command finds the account with any casing of the address
        $user = User::query()->where('email', mb_strtolower($this->argument('email')))->first();
        if ($user === null) {
            $this->error('No account has this email address.');

            return self::FAILURE;
        }

        $this->line($exporter->export($user));

        return self::SUCCESS;
    }
}
