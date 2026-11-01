<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objectifs', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 40);          // clé de l'indicateur (voir Objectif::METRICS)
            $table->string('period', 10);          // month | year
            $table->string('ref', 10);             // 2026-11 (mois) ou 2026 (année)
            $table->decimal('target', 14, 2);      // valeur à atteindre (à ne pas dépasser pour CAC / dépenses)
            $table->timestamps();

            $table->unique(['metric', 'period', 'ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objectifs');
    }
};