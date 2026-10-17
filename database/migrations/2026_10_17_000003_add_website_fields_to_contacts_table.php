<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('website_user_id')->nullable()->unique()->after('id');
            $table->text('interests')->nullable();
            $table->timestamp('registered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['website_user_id']);
            $table->dropColumn(['website_user_id', 'interests', 'registered_at']);
        });
    }
};