<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketing_spends', function (Blueprint $t) {
            $t->id();
            $t->date('spent_on')->index();
            $t->string('channel', 30)->default('facebook')->index();
            $t->unsignedBigInteger('amount');          // en Ariary
            $t->string('label', 150)->nullable();
            $t->timestamps();
        });

        Schema::create('facebook_insights', function (Blueprint $t) {
            $t->id();
            $t->date('date')->unique();
            $t->unsignedBigInteger('views')->default(0);        // vues (page_media_view)
            $t->unsignedBigInteger('reach')->default(0);        // personnes atteintes
            $t->unsignedBigInteger('engagements')->default(0);
            $t->unsignedBigInteger('follows')->default(0);      // nouveaux abonnés
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_insights');
        Schema::dropIfExists('marketing_spends');
    }
};