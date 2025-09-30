<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->json('body'); // Rich editor content
            $table->json('photos')->nullable(); // Array of photo URLs
            $table->json('videos')->nullable(); // Array of video URLs
            $table->json('social_medias'); // Selected platforms
            $table->timestamp('schedule_time')->nullable();
            $table->enum('status', ['draft', 'processing_media', 'ready_to_publish', 'scheduled', 'publishing', 'published', 'failed'])->default('draft');
            $table->json('publication_status')->nullable(); // Platform-specific statuses
            $table->integer('total_platforms')->default(0);
            $table->integer('published_platforms')->default(0);
            $table->integer('failed_platforms')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
