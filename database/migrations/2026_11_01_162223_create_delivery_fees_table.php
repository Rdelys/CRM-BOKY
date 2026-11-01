<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_fees', function (Blueprint $table) {
            $table->id();
            $table->string('quartier');                 // ex : Ankadifotsy
            $table->string('lieu_precis')->nullable();  // ex : Près de l'école, Lot II A 12 (optionnel)
            $table->unsignedInteger('frais');           // en Ariary
            $table->string('note')->nullable();         // ex : Livraison sous 24h
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index('quartier');
        });

        // Jeton du lien public partagé (une seule ligne)
        Schema::create('delivery_share_settings', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_share_settings');
        Schema::dropIfExists('delivery_fees');
    }
};