<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An email address belongs to one account, without regard to the case (#1706, ADR 0058): a unique index on lower(email).
 * The sign-in link, the provider and the profile find an account by its address that way, so two accounts must never have
 * one address in two cases. The index of users.email stays.
 *
 * Two accounts that have one address in two cases stop the migration, with their cuids: an operator decides which account
 * keeps the address. The migration deletes and changes nothing.
 */
return new class extends Migration
{
    public const string INDEX = 'users_email_lower_unique';

    public function up(): void
    {
        $duplicates = DB::table('users')
            ->selectRaw('lower(email) as address, string_agg(cuid, \', \' order by id) as cuids')
            ->groupByRaw('lower(email)')
            ->havingRaw('count(*) > 1')
            ->pluck('cuids');
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Some accounts have the same email address in another case, so the address cannot be made unique without the case. Give one account of each group another address, then run the migration again. The accounts (cuids), one group per line:%s%s',
                PHP_EOL,
                $duplicates->implode(PHP_EOL),
            ));
        }

        DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON users (lower(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
};
