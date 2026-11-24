<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A personal community is the place of one person and has no public page (ADR 0044): a space of a personal community
 * is never public. The actions refuse it (SpaceInputRules); this migration turns the public spaces that personal
 * communities already have into spaces for members, which is the owner alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('spaces')
            ->where('visibility', 'public')
            ->whereIn('community_id', DB::table('communities')->select('id')->where('visibility', 'personal'))
            ->update(['visibility' => 'members']);
    }

    /**
     * Nothing to bring back: which spaces were public is not kept
     */
    public function down(): void {}
};
