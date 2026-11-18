<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Events (#1513, ADR 0053): a space type `events` and a table of its own, like the resources of ADR 0015.
 *
 * - spaces get the type "events";
 * - events: one dated item of an events space. The start and the end are UTC instants (the truth); `time_zone` is the
 *   zone in which the host entered the time. "Past" is not stored: it is derived from the end;
 * - upload_usages.event_id: the cover and the images of the description stay while the event shows them (ADR 0014);
 * - notifications.event_id: the item of an `event_new` row (ADR 0042, ADR 0014: a new kind of item adds a column).
 *
 * The value lists are literals: the enums change over time, a migration must not.
 */
return new class extends Migration
{
    private const array SPACE_TYPES = ['discussion', 'chat', 'pages', 'course', 'resources', 'link', 'events'];

    private const array EVENT_STATES = ['draft', 'published', 'cancelled'];

    private const array LOCATION_VISIBILITIES = ['members', 'going'];

    private const array UPLOAD_USAGE_ITEMS = ['post_id', 'comment_id', 'page_id', 'chat_message_id'];

    private const array NOTIFICATION_ITEMS = ['post_id', 'comment_id', 'chat_message_id', 'page_id'];

    public function up(): void
    {
        self::replaceTypeCheck(self::SPACE_TYPES);

        Schema::create('events', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            // One host. A deleted account keeps the event, without a host ("Deleted member"), like the author of a post
            $table->foreignId('host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            // Unique within the space, never changed by a rename
            $table->string('slug');
            $table->text('body');
            $table->text('body_html');
            // UTC instants
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            // The IANA zone in which the host entered the time; it never changes the moment
            $table->string('time_zone');
            $table->string('meeting_url', 2048)->nullable();
            $table->string('place', 255)->nullable();
            // A backed enum (KnowiiEventLocationVisibility)
            $table->string('location_visibility')->default('members');
            // A deleted upload removes the cover, not the event
            $table->foreignId('cover_upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            // Empty: a decorative image
            $table->string('cover_alt', 125)->nullable();
            // A backed enum (KnowiiEventState)
            $table->string('state')->default('draft');
            // Goes up when the time, the address, the place or the state changes (the calendar file of a later slice reads it)
            $table->integer('sequence')->default(0);
            // When the "new event" lines were queued: they are queued once
            $table->timestampTz('published_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['space_id', 'slug']);
            $table->index(['space_id', 'starts_at']);
        });
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_state_check CHECK (state IN ('.self::quoted(self::EVENT_STATES).'))');
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_location_visibility_check CHECK (location_visibility IN ('.self::quoted(self::LOCATION_VISIBILITIES).'))');
        // The published events by start: the lists across spaces, and later the reminders
        DB::statement("CREATE INDEX events_published_starts_at_index ON events (starts_at) WHERE state = 'published'");

        Schema::table('upload_usages', static function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['upload_id', 'event_id']);
            $table->index('event_id');
        });
        self::replaceItemCheck('upload_usages', [...self::UPLOAD_USAGE_ITEMS, 'event_id']);

        Schema::table('notifications', static function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->index('event_id');
        });
        self::replaceItemCheck('notifications', [...self::NOTIFICATION_ITEMS, 'event_id']);
        // A retry of the fan-out never writes a second row for the same event
        DB::statement('CREATE UNIQUE INDEX notifications_event_unique ON notifications (user_id, kind, event_id) WHERE event_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS notifications_event_unique');
        DB::table('notifications')->whereNotNull('event_id')->delete();
        self::replaceItemCheck('notifications', self::NOTIFICATION_ITEMS);
        Schema::table('notifications', static function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });

        DB::table('upload_usages')->whereNotNull('event_id')->delete();
        self::replaceItemCheck('upload_usages', self::UPLOAD_USAGE_ITEMS);
        Schema::table('upload_usages', static function (Blueprint $table) {
            $table->dropUnique(['upload_id', 'event_id']);
            $table->dropConstrainedForeignId('event_id');
        });

        Schema::dropIfExists('events');

        DB::table('spaces')->where('type', 'events')->delete();
        self::replaceTypeCheck(array_values(array_diff(self::SPACE_TYPES, ['events'])));
    }

    /**
     * @param  list<string>  $values
     */
    private static function replaceTypeCheck(array $values): void
    {
        DB::statement('ALTER TABLE spaces DROP CONSTRAINT IF EXISTS spaces_type_check');
        DB::statement('ALTER TABLE spaces ADD CONSTRAINT spaces_type_check CHECK (type IN ('.self::quoted($values).'))');
    }

    /**
     * @param  list<string>  $columns
     */
    private static function replaceItemCheck(string $table, array $columns): void
    {
        $constraint = $table.'_one_item';
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (num_nonnulls(".implode(', ', $columns).') = 1)');
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
