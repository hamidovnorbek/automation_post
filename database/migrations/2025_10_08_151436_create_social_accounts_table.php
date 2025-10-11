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
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('platform_name', ['facebook', 'instagram', 'telegram', 'youtube']);
            $table->text('access_token'); // Will be encrypted
            $table->text('refresh_token')->nullable(); // Will be encrypted
            $table->timestamp('expires_at')->nullable();
            $table->string('account_username')->nullable();
            $table->json('meta')->nullable(); // For storing profile info
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Ensure one account per platform per user
            $table->unique(['user_id', 'platform_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
