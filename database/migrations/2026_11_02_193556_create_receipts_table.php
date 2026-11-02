<?php

use App\Enums\ReceiptStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            // Opaque, non-sequential public number for use on PDFs / verification links.
            $table->string('public_number', 32)->unique();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            // Immutable snapshots — the receipt is a frozen document; the live
            // models can change afterwards without touching what was issued.
            $table->json('organizer_snapshot');
            $table->json('event_snapshot');
            $table->json('attendee_snapshot');

            $table->unsignedInteger('subtotal_minor')->default(0);
            $table->unsignedInteger('discount_minor')->default(0);
            $table->unsignedInteger('tax_minor')->default(0);
            $table->unsignedInteger('fee_minor')->default(0);
            $table->unsignedInteger('total_minor')->default(0);
            $table->char('currency', 3);

            $table->string('status', 32)->default(ReceiptStatus::Issued->value);
            $table->string('pdf_path')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('voided_at')->nullable();
            $table->string('voided_reason')->nullable();
            $table->timestamps();

            $table->index(['registration_id']);
            $table->index(['payment_id']);
            $table->index(['status', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
