<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nom', 120)->nullable();
            $table->string('telephone', 20);               // format local 0340000000
            $table->text('message');
            $table->string('origine', 20)->default('contacts'); // contacts | classement
            $table->string('statut', 10)->default('sent')->index(); // sent | failed
            $table->string('erreur')->nullable();
            $table->unsignedTinyInteger('nb_sms')->default(1);
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};