<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An invitation carries access groups and member tags (#1390): the person gets them when they accept it. The
 * migration only adds two link tables: it changes no existing row. A link has no cuid: no route and no answer names one
 * (as member_tag_members).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_invitation_access_groups', static function (Blueprint $table) {
            $table->id();
            // A deleted invitation (accepted, cancelled, replaced, pruned) takes its links
            $table->foreignId('community_invitation_id')->constrained()->cascadeOnDelete();
            // A deleted access group leaves the invitation: the invitation stays, without that group
            $table->foreignId('access_group_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['community_invitation_id', 'access_group_id']);
            $table->index('access_group_id');
        });

        Schema::create('community_invitation_member_tags', static function (Blueprint $table) {
            $table->id();
            // A deleted invitation takes its links
            $table->foreignId('community_invitation_id')->constrained()->cascadeOnDelete();
            // A deleted tag leaves the invitation (the contract of ADR 0060, point 4)
            $table->foreignId('member_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['community_invitation_id', 'member_tag_id']);
            $table->index('member_tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_invitation_member_tags');
        Schema::dropIfExists('community_invitation_access_groups');
    }
};
