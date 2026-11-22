<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invite links (#1391, ADR 0062): an address that the managers of a community share, through which any person joins as a
 * member, with the access groups and the member tags of the link. The migration only adds four tables: it changes no
 * existing row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invite_links', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            // A deleted community takes its links with it
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            // sha256 of the token: every lookup hashes the token first
            $table->string('token_hash', 64)->unique();
            // The token itself, encrypted with the key of the application, so that the managers copy the link again
            $table->text('token');
            $table->boolean('active')->default(true);
            $table->timestampTz('expires_at')->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('uses_count')->default(0);
            $table->timestamps();

            $table->index('community_id');
        });

        DB::statement('ALTER TABLE invite_links ADD CONSTRAINT invite_links_max_uses_check CHECK (max_uses IS NULL OR max_uses >= 1)');
        DB::statement('ALTER TABLE invite_links ADD CONSTRAINT invite_links_uses_count_check CHECK (uses_count >= 0)');

        // The shape of the link tables of the invitations (#1390): no cuid, no route names a row
        Schema::create('invite_link_access_groups', static function (Blueprint $table) {
            $table->id();
            // A deleted link takes its rows
            $table->foreignId('invite_link_id')->constrained()->cascadeOnDelete();
            // A deleted access group leaves the link: the link stays, without that group
            $table->foreignId('access_group_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['invite_link_id', 'access_group_id']);
            $table->index('access_group_id');
        });

        Schema::create('invite_link_member_tags', static function (Blueprint $table) {
            $table->id();
            // A deleted link takes its rows
            $table->foreignId('invite_link_id')->constrained()->cascadeOnDelete();
            // A deleted tag leaves the link (the contract of ADR 0060, point 4)
            $table->foreignId('member_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['invite_link_id', 'member_tag_id']);
            $table->index('member_tag_id');
        });

        // One row per join through a link: the link, the member and the date. No cuid and no updated_at, as
        // access_group_members: the row is never public on its own, and it never changes
        Schema::create('invite_link_uses', static function (Blueprint $table) {
            $table->id();
            // A deleted link takes the list of who joined through it
            $table->foreignId('invite_link_id')->constrained()->cascadeOnDelete();
            // A deleted account takes its rows
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['invite_link_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_link_uses');
        Schema::dropIfExists('invite_link_member_tags');
        Schema::dropIfExists('invite_link_access_groups');
        Schema::dropIfExists('invite_links');
    }
};
