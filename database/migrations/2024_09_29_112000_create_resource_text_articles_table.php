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

      // Content converted to Markdown
      $table->text('markdown')->nullable();

      // Capture the html of the page
      $table->text('html')->nullable();

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
