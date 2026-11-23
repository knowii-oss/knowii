<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A report of the mail provider on an invitation email (#1478, ADR 0054): when (`bounced_at`) and how (`bounce_type`:
 * `hard` or `soft`). It never suppresses an account. A new send of the same invitation clears both; a replace makes a new
 * invitation without them. The columns go with the invitation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_invitations', static function (Blueprint $table) {
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_type', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('community_invitations', static function (Blueprint $table) {
            $table->dropColumn(['bounced_at', 'bounce_type']);
        });
    }
};
