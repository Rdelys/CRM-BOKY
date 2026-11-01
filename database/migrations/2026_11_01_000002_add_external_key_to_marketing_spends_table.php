<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_spends', function (Blueprint $table) {
            // Clé de dédoublonnage des dépenses importées (ex : "fb_ads:2026-11-01").
            // Les dépenses saisies à la main gardent NULL (plusieurs NULL autorisés par l'index unique).
            $table->string('external_key', 60)->nullable()->unique()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_spends', function (Blueprint $table) {
            $table->dropUnique(['external_key']);
            $table->dropColumn('external_key');
        });
    }
};