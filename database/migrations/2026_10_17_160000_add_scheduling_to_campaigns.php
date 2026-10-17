<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedSmallInteger('per_minute')->default(30);
        });

        // Nouveaux statuts : scheduled / cancelled (campagnes), pending / skipped / cancelled (messages)
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->change();
        });

        Schema::table('message_logs', function (Blueprint $table) {
            $table->dateTime('sent_at')->nullable();
        });

        Schema::table('message_logs', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('message_logs', function (Blueprint $table) {
            $table->dropIndex(['campaign_id', 'status']);
            $table->dropColumn('sent_at');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at', 'per_minute']);
        });
    }
};