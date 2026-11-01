<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->unsignedInteger('frais_livraison')->default(0)->after('montant_total');
        });
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('frais_livraison');
        });
    }
};