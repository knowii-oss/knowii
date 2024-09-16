<?php

use App\KnowiiCommunityVisibility;
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
            $table->string('cuid');
            $table->foreignId('user_id')->index();
            $table->string('name')->index();
            $table->string('description')->nullable();
            $table->enum('visibility', KnowiiCommunityVisibility::toStringArray());
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
