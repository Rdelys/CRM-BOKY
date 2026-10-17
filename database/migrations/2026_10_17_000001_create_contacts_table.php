<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('source')->default('direct');   // website, facebook, whatsapp, direct, other
            $table->string('status')->default('prospect'); // prospect, contacted, client, lost
            $table->string('city')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('unsubscribed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
