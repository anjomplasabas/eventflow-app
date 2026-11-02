<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            // Opaque token used for QR check-in; never expose the auto-increment id publicly.
            $table->char('public_token', 32)->unique();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('attendee_name');
            $table->string('attendee_email');
            $table->string('status', 16)->default('issued');
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->string('qr_image_path')->nullable();
            $table->timestamps();

            $table->index(['registration_id']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
