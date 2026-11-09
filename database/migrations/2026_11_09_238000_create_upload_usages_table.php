<?php

use App\Support\UploadReferences;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which post or comment uses which upload (#1201), with explicit foreign keys (ADR 0014). An upload that nothing uses
 * for a day is deleted by `uploads:prune`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_usages', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained()->cascadeOnDelete();

            $table->unique(['upload_id', 'post_id']);
            $table->unique(['upload_id', 'comment_id']);
            $table->index('post_id');
            $table->index('comment_id');
        });

        DB::statement('ALTER TABLE upload_usages ADD CONSTRAINT upload_usages_one_item CHECK (num_nonnulls(post_id, comment_id) = 1)');

        // The posts and comments written before: without their usages, the prune would delete images in use
        foreach (['posts' => 'post_id', 'comments' => 'comment_id'] as $items => $column) {
            DB::table($items)->select(['id', 'body'])->orderBy('id')->chunk(500, static function ($rows) use ($column) {
                foreach ($rows as $row) {
                    $cuids = is_string($row->body) ? UploadReferences::cuidsIn($row->body) : [];
                    if ($cuids === []) {
                        continue;
                    }
                    foreach (DB::table('uploads')->whereIn('cuid', $cuids)->pluck('id') as $uploadId) {
                        DB::table('upload_usages')->insertOrIgnore(['upload_id' => $uploadId, $column => $row->id]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_usages');
    }
};
