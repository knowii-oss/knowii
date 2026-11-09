<?php

use App\Enums\KnowiiResourceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resource types become a fixed list (ADR 0018 step 3, #1246): article, video, audio, book, paper, tool, other.
 * "textArticle" becomes "article". community_resources.type_chosen says that the curator chose the type: a new
 * capture then keeps it.
 */
return new class extends Migration
{
    private const array TABLES = ['resources', 'community_resources'];

    public function up(): void
    {
        $this->replaceTypes(KnowiiResourceType::toStringArray(), ['textArticle' => 'article'], 'other');

        Schema::table('community_resources', static function (Blueprint $table) {
            $table->boolean('type_chosen')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('community_resources', static function (Blueprint $table) {
            $table->dropColumn('type_chosen');
        });

        $mapping = [];
        foreach (KnowiiResourceType::toStringArray() as $type) {
            $mapping[$type] = 'textArticle';
        }
        $this->replaceTypes(['textArticle'], $mapping, 'textArticle');
    }

    /**
     * @param  list<string>  $allowed
     * @param  array<string, string>  $mapping
     */
    private function replaceTypes(array $allowed, array $mapping, string $default): void
    {
        $list = implode(', ', array_map(static fn (string $type) => DB::getPdo()->quote($type), $allowed));

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_type_check");
            foreach ($mapping as $from => $to) {
                DB::table($table)->where('type', $from)->update(['type' => $to]);
            }
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_type_check CHECK (type IN ({$list}))");
        }

        DB::statement('ALTER TABLE community_resources ALTER COLUMN type SET DEFAULT '.DB::getPdo()->quote($default));
    }
};
