<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The answers of members to events, and the limit of places (#1515, ADR 0053, "The answers and the places"):
 *
 * - events.capacity: the limit of places, 1 to 5 000, or null for no limit. It counts the Yes answers only;
 * - events.replay_expected: the host says that a replay will exist, which offers the answer "Replay only";
 * - event_answers: one row per member and event. No row means no answer; a "No" is a row.
 *
 * The value lists are literals: the enums change over time, a migration must not.
 */
return new class extends Migration
{
    private const array ANSWERS = ['yes', 'replay', 'no'];

    public function up(): void
    {
        Schema::table('events', static function (Blueprint $table) {
            // Null: no limit
            $table->integer('capacity')->nullable();
            $table->boolean('replay_expected')->default(false);
        });
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_capacity_check CHECK (capacity IS NULL OR capacity BETWEEN 1 AND 5000)');

        Schema::create('event_answers', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            // An answer has no use without its event: a deleted event deletes its answers
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // An answer is personal data of its member: a deleted account deletes its answers (ADR 0024)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The same community as the event: a member who leaves loses their answers in it with one delete, and a
            // deleted community deletes them
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            // A backed enum (KnowiiEventAnswer)
            $table->string('answer');
            $table->timestamps();

            // One answer per member and event: a second row is refused, also when two requests arrive at once
            $table->unique(['event_id', 'user_id']);
            // The events that a member answered, across communities
            $table->index(['user_id', 'answer']);
            // The counts of an event (the places that are left)
            $table->index(['event_id', 'answer']);
            // The answers of a community: the delete of a community (the cascade) and the leave of one of its members
            $table->index('community_id');
        });
        DB::statement('ALTER TABLE event_answers ADD CONSTRAINT event_answers_answer_check CHECK (answer IN ('.self::quoted(self::ANSWERS).'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('event_answers');

        DB::statement('ALTER TABLE events DROP CONSTRAINT IF EXISTS events_capacity_check');
        Schema::table('events', static function (Blueprint $table) {
            $table->dropColumn(['capacity', 'replay_expected']);
        });
    }

    /**
     * @param  list<string>  $values
     */
    private static function quoted(array $values): string
    {
        $pdo = DB::getPdo();

        return implode(', ', array_map(static fn (string $value) => $pdo->quote($value), $values));
    }
};
