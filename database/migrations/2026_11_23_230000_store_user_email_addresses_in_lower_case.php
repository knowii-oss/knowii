<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every email address of an account is stored in lower case (User::email(), User::pendingEmail(), UserProfile::email()),
 * so a person signs in and resets the password with any casing of the address. This migration writes the stored rows in
 * lower case: users.email, users.pending_email and the copy of the address in user_profiles.email.
 *
 * Two rows that have one address in two cases stop the migration, with their cuids: an operator decides which one keeps
 * the address. The migration then changes nothing. The unique index on lower(users.email) (2026_11_21_091000) already
 * refuses two accounts of that kind; the copy of the profiles is checked too. The indexes stay as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(static function (): void {
            foreach (['users', 'user_profiles'] as $table) {
                $duplicates = DB::table($table)
                    ->whereNotNull('email')
                    ->selectRaw('string_agg(cuid, \', \' order by id) as cuids')
                    ->groupByRaw('lower(email)')
                    ->havingRaw('count(*) > 1')
                    ->pluck('cuids');
                if ($duplicates->isNotEmpty()) {
                    throw new RuntimeException(sprintf(
                        'Some rows of %s have the same email address in another case, so the addresses cannot be stored in lower case. Give one row of each group another address, then run the migration again. The rows (cuids), one group per line:%s%s',
                        $table,
                        PHP_EOL,
                        $duplicates->implode(PHP_EOL),
                    ));
                }
            }

            DB::table('users')->whereRaw('email <> lower(email)')->update(['email' => DB::raw('lower(email)')]);
            DB::table('users')->whereNotNull('pending_email')->whereRaw('pending_email <> lower(pending_email)')->update(['pending_email' => DB::raw('lower(pending_email)')]);
            DB::table('user_profiles')->whereNotNull('email')->whereRaw('email <> lower(email)')->update(['email' => DB::raw('lower(email)')]);
        });
    }

    /**
     * The case of before is not kept: nothing to undo
     */
    public function down(): void {}
};
