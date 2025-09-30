<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->enum('platform', ['facebook', 'instagram', 'telegram']);
            $table->enum('status', ['pending', 'publishing', 'published', 'failed', 'scheduled'])->default('pending');
            $table->string('external_id')->nullable(); // Platform's post ID
            $table->text('platform_url')->nullable(); // Direct link to the published post
            $table->json('response_data')->nullable(); // API response data
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->unique(['post_id', 'platform']);
            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_publications');
    }
};
