<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->dateTime('date_commande')->index();              // Horodateur
            $table->text('adresse_livraison')->nullable();           // Adresse de livraison
            $table->text('livres')->nullable();                      // Livre(s) commandé(s)
            $table->string('mode_paiement', 60)->nullable();         // Paiement
            $table->date('date_livraison')->nullable()->index();     // Date de livraison
            $table->string('heure_livraison', 30)->nullable();       // Heure de livraison
            $table->string('precision_heure', 255)->nullable();      // Précision de l'heure
            $table->string('responsable', 120)->nullable();          // Responsable
            $table->unsignedBigInteger('montant_total')->default(0); // Montant total (Ar)
            $table->string('statut', 30)->default('confirmee')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};