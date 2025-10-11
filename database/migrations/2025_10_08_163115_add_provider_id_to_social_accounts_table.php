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
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('provider_id')->nullable()->after('platform_name');
            $table->string('provider_name')->nullable()->after('provider_id'); // For display purposes
            $table->index(['provider_id', 'platform_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropIndex(['provider_id', 'platform_name']);
            $table->dropColumn(['provider_id', 'provider_name']);
        });
    }
};
