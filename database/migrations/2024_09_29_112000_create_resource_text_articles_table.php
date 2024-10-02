<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('resource_text_articles', static function (Blueprint $table) {
      $table->id();
      $table->string('cuid')->unique()->index();

      // If the global resource is deleted, then this is deleted as well
      $table->foreignId('resource_id')->constrained()->cascadeOnDelete();

      // Capture the content of the resource
      $table->text('content')->nullable();

      $table->integer('word_count')->nullable();

      // Reading time in minutes
      $table->integer('reading_time')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('resource_text_articles');
  }
};
