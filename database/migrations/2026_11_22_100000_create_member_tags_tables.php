<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Member tags (#1389, ADR 0060): labels that the managers of a community put on its members, for example "Founder". The
 * migration only adds two tables: it changes no existing row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_tags', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            // A deleted community takes its tags with it
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('name', 32);
            $table->string('color')->default('gray');
            $table->string('visibility')->default('managers');
            $table->timestamps();
        });

        // The value lists are literals: a later change of the enums needs its own migration
        DB::statement("ALTER TABLE member_tags ADD CONSTRAINT member_tags_color_check CHECK (color IN ('gray','red','orange','yellow','green','teal','blue','purple'))");
        DB::statement("ALTER TABLE member_tags ADD CONSTRAINT member_tags_visibility_check CHECK (visibility IN ('members','managers'))");
        // One name per community, whatever the case: "Founder" and "founder" are the same tag (the import of #1390 names tags
        // by their name). The action checks it first and answers with a validation error; the index stops two requests at once
        DB::statement('CREATE UNIQUE INDEX member_tags_community_name_unique ON member_tags (community_id, lower(name))');

        Schema::create('member_tag_members', static function (Blueprint $table) {
            $table->id();
            // A deleted tag leaves every member who had it
            $table->foreignId('member_tag_id')->constrained()->cascadeOnDelete();
            // The link is on the user, not on the row of community_members: the owner has no such row, and a transfer of the
            // ownership moves those rows (ADR 0035). A deleted account loses its tags
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['member_tag_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_tag_members');
        Schema::dropIfExists('member_tags');
    }
};
