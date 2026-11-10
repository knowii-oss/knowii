<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Simple links (#1294, ADR 0026):
 * - spaces get the type "link" and a url, required for that type only (a navigation link to a page outside Knowii);
 * - community resources get the capture status "skipped": the curator kept only the link, Knowii never opened it.
 *
 * The value lists are literals: the enums change over time, a migration must not.
 */
return new class extends Migration
{
    private const array SPACE_TYPES = ['discussion', 'chat', 'pages', 'course', 'resources', 'link'];

    private const array CAPTURE_STATUSES = ['pending', 'capturing', 'captured', 'failed', 'skipped'];

    public function up(): void
    {
        Schema::table('spaces', static function (Blueprint $table) {
            $table->string('url', 2048)->nullable()->after('emoji');
        });

        self::replaceCheck('spaces', 'type', self::SPACE_TYPES);
        DB::statement("ALTER TABLE spaces ADD CONSTRAINT spaces_link_url_check CHECK ((type = 'link') = (url IS NOT NULL))");

        self::replaceCheck('community_resources', 'capture_status', self::CAPTURE_STATUSES);
    }

    public function down(): void
    {
        DB::table('spaces')->where('type', 'link')->delete();
        DB::statement('ALTER TABLE spaces DROP CONSTRAINT IF EXISTS spaces_link_url_check');
        self::replaceCheck('spaces', 'type', array_values(array_diff(self::SPACE_TYPES, ['link'])));
        Schema::table('spaces', static function (Blueprint $table) {
            $table->dropColumn('url');
        });

        DB::table('community_resources')->where('capture_status', 'skipped')->update(['capture_status' => 'captured']);
        self::replaceCheck('community_resources', 'capture_status', array_values(array_diff(self::CAPTURE_STATUSES, ['skipped'])));
    }

    /**
     * @param  list<string>  $values
     */
    private static function replaceCheck(string $table, string $column, array $values): void
    {
        $pdo = DB::getPdo();
        $list = implode(', ', array_map(static fn (string $value) => $pdo->quote($value), $values));

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_{$column}_check");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column} IN ({$list}))");
    }
};
