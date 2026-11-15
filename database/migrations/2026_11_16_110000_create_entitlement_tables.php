<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The entitlements of the sign-in provider feed the access groups (#1386, ADR 0047).
 *
 * - `user_entitlements`: the active keys of a person at the provider, replaced as a whole at each read. Only the key: no
 *   grant, source or date of the provider.
 * - `user_identities.entitlements_checked_at`: when the keys were last read.
 * - `access_group_rules`: "this entitlement key gives this access group".
 * - `access_group_members.manual` and `by_rule`: where a membership comes from. Every existing row was added by hand. A
 *   row exists while one of the two is true (a check constraint), so the reading rule of ADR 0041 does not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_entitlements', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('issuer', 512);
            $table->string('key', 100);

            $table->unique(['user_id', 'issuer', 'key']);
        });

        Schema::table('user_identities', static function (Blueprint $table) {
            $table->timestamp('entitlements_checked_at')->nullable();
        });

        Schema::create('access_group_rules', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_group_id')->constrained()->cascadeOnDelete();
            $table->string('entitlement_key', 100);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['access_group_id', 'entitlement_key']);
            $table->index('entitlement_key');
        });

        Schema::table('access_group_members', static function (Blueprint $table) {
            // Added by hand or by the API: the value of every row that exists now
            $table->boolean('manual')->default(true);
            $table->boolean('by_rule')->default(false);
        });
        DB::statement('ALTER TABLE access_group_members ADD CONSTRAINT access_group_members_source_check CHECK (manual OR by_rule)');
    }

    public function down(): void
    {
        // A membership that only a rule gave goes away: nothing keeps it without the rules
        DB::table('access_group_members')->where('manual', false)->delete();
        DB::statement('ALTER TABLE access_group_members DROP CONSTRAINT IF EXISTS access_group_members_source_check');
        Schema::table('access_group_members', static function (Blueprint $table) {
            $table->dropColumn(['manual', 'by_rule']);
        });

        Schema::dropIfExists('access_group_rules');

        Schema::table('user_identities', static function (Blueprint $table) {
            $table->dropColumn('entitlements_checked_at');
        });

        Schema::dropIfExists('user_entitlements');
    }
};
