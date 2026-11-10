<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The name of a shared community is unique, whatever the case (ADR 0033, #1136). The actions check it first and
 * answer with a validation error; this index stops two requests that create the same name at the same time.
 * Personal communities are left out: two members with the same name each have their own space.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX communities_shared_name_unique ON communities (lower(name)) WHERE visibility <> 'personal'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS communities_shared_name_unique');
    }
};
