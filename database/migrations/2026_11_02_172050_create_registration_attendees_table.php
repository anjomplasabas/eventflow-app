<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->json('custom_answers')->nullable();
            $table->timestamps();

            $table->index(['registration_id']);
            $table->index(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_attendees');
    }
};
