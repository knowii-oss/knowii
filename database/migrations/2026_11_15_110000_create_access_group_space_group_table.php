<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Access groups can open a whole space group (#1422, ADR 0041). Explicit foreign keys, like access_group_space
 * (ADR 0014). Both rows must belong to the same community: the actions check it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_group_space_group', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_group_id')->constrained()->cascadeOnDelete();

            $table->unique(['space_group_id', 'access_group_id']);
            $table->index('access_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_group_space_group');
    }
};
