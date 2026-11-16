<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satisfactions', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 120);
            $table->string('livre', 190)->nullable();
            $table->unsignedTinyInteger('note');            // 1 à 5
            $table->text('commentaire')->nullable();
            $table->string('source', 30)->default('direct')->index(); // d'où vient le clic
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfactions');
    }
};