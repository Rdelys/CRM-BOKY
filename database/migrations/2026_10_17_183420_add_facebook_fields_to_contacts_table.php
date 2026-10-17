<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $t) {
            $t->string('facebook_psid')->nullable()->unique();
            $t->timestamp('last_fb_interaction_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $t) {
            $t->dropUnique(['facebook_psid']);
            $t->dropColumn([
                'facebook_psid',
                'last_fb_interaction_at',
            ]);
        });
    }
};