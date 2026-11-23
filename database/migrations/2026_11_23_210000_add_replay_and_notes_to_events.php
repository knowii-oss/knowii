<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * After the event (#1516, ADR 0053): the replay link that the host pastes (the platform never receives a recording) and
 * the notes in Markdown, with their HTML rendered on the server (ADR 0013). An empty text means no notes, as for `body`.
 * Community content: kept with the event, in the community export, deleted with the space or the community.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', static function (Blueprint $table) {
            $table->string('replay_url', 2048)->nullable()->after('replay_expected');
            $table->text('notes')->default('')->after('replay_url');
            $table->text('notes_html')->default('')->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('events', static function (Blueprint $table) {
            $table->dropColumn(['replay_url', 'notes', 'notes_html']);
        });
    }
};
