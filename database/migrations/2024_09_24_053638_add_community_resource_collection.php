<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('community_resource_collections', static function (Blueprint $table) {
        $table->id();
        $table->string('cuid');
        $table->foreignId('community_id')->constrained()->cascadeOnDelete();
        $table->string('name')->index();
        $table->string('slug')->unique()->index();
        $table->string('description')->nullable();
        $table->timestamps();
      });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
      Schema::dropIfExists('community_resource_collections');
    }
};
