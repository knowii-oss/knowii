<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\KnowiiCommunityMemberRole;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('community_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('cuid');
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->enum('role', KnowiiCommunityMemberRole::toStringArray());
            $table->timestamps();

            $table->unique(['community_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_invitations');
    }
};
