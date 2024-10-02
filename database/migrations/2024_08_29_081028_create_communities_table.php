<?php

use App\Enums\KnowiiCommunityVisibility;
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
        Schema::create('communities', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique()->index();
            $table->unsignedBigInteger('owner_id');
            $table->foreign('owner_id')
              ->references('id')
              ->on('users')
              ->cascadeOnDelete();
            $table->string('name')->index();
            $table->string('slug')->unique()->index();
            $table->string('description')->nullable();
            $table->enum('visibility', KnowiiCommunityVisibility::toStringArray())->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communities');
    }
};
