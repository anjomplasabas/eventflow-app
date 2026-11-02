<?php

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 64);
            $table->string('provider_reference')->nullable();
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('fee_minor')->default(0);
            $table->string('status', 32)->default(PaymentStatus::Pending->value);
            $table->json('method_summary')->nullable();
            $table->string('raw_event_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['registration_id']);
            $table->index(['provider', 'provider_reference']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
