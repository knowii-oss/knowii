<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Images that members upload for their posts and comments (#1178). The files live on the uploads disk
 * (config/filesystems.php: public by default, an S3-compatible bucket later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploads', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            // Files stay when the uploader deletes the account: they can be in posts of other members (quotes)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('size_bytes');
            $table->string('alt', 500);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['community_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
