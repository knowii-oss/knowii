<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations from a file (#1390, ADR 0061): the record of an import and one row per data row of the file. The file
 * itself is never stored. The migration only adds two tables: it changes no existing row.
 *
 * The names of the groups and the tags of a row, and the defaults of the import, are jsonb lists and not link tables: a
 * preview lives 24 hours and the confirm resolves every name and cuid again. The lasting links are those of the
 * invitations, with their foreign keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_imports', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            // A deleted community takes its imports with it
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            // The person who uploaded the file. A deleted account leaves the record, without its author
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('state');
            $table->string('default_role');
            $table->jsonb('default_access_groups');
            $table->jsonb('default_member_tags');
            $table->jsonb('ignored_columns');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            // The one "Invite again the people who did not join"
            $table->timestamp('resent_at')->nullable();
            $table->timestamps();

            $table->index(['community_id', 'created_at']);
        });

        // The value lists are literals: a later change of the enums needs its own migration
        DB::statement("ALTER TABLE invitation_imports ADD CONSTRAINT invitation_imports_state_check CHECK (state IN ('preview','sending','done'))");
        DB::statement("ALTER TABLE invitation_imports ADD CONSTRAINT invitation_imports_default_role_check CHECK (default_role IN ('admin','moderator','member'))");

        Schema::create('invitation_import_rows', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            // A deleted import takes its rows
            $table->foreignId('invitation_import_id')->constrained()->cascadeOnDelete();
            // The number of the record in the file: the header is 1
            $table->unsignedInteger('row_number');
            // The cell in lower case, cut to 255 characters, also when it is not a valid address: the manager sees what to fix
            $table->string('email', 255);
            // Null when the cell names no role of the list
            $table->string('role')->nullable();
            // The cleaned names of the row, with the defaults
            $table->jsonb('access_group_names');
            $table->jsonb('member_tag_names');
            $table->string('outcome');
            $table->string('reason')->nullable();
            // The name that was not found, the earlier row of the same address, or the end of a pending invitation
            $table->string('detail', 64)->nullable();
            // An accepted or cancelled invitation leaves the row
            $table->foreignId('community_invitation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('queued_at')->nullable();
            // How many times the row went to the queue: the job of the row holds the number that it was queued with
            $table->unsignedInteger('queued_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['invitation_import_id', 'row_number']);
            // The 24 hours of a community count the rows by their queue time
            $table->index(['invitation_import_id', 'queued_at']);
        });

        DB::statement("ALTER TABLE invitation_import_rows ADD CONSTRAINT invitation_import_rows_role_check CHECK (role IS NULL OR role IN ('admin','moderator','member'))");
        DB::statement("ALTER TABLE invitation_import_rows ADD CONSTRAINT invitation_import_rows_outcome_check CHECK (outcome IN ('valid','refused','queued','sent','held','failed','skipped'))");
        DB::statement("ALTER TABLE invitation_import_rows ADD CONSTRAINT invitation_import_rows_reason_check CHECK (reason IS NULL OR reason IN ('extra_cells','cell_too_long','invalid_email','duplicate_in_file','unknown_role','role_not_allowed','access_groups_not_allowed','unknown_access_group','ambiguous_access_group','tags_not_allowed','unknown_tag','too_many_tags','already_member','removed_by_manager','pending_invitation'))");
        // The account deletion and the member export find the rows of an address without the case
        DB::statement('CREATE INDEX invitation_import_rows_email_lower_index ON invitation_import_rows (lower(email))');
        DB::statement('CREATE INDEX invitation_import_rows_community_invitation_id_index ON invitation_import_rows (community_invitation_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_import_rows');
        Schema::dropIfExists('invitation_imports');
    }
};
