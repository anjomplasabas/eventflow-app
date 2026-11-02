<?php

use App\Enums\TicketKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('kind', 16)->default(TicketKind::Free->value);
            $table->unsignedInteger('price_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('min_per_order')->default(1);
            $table->unsignedInteger('max_per_order')->default(1);
            $table->timestamp('sales_opens_at')->nullable();
            $table->timestamp('sales_closes_at')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['event_id', 'name']);
            $table->index(['event_id', 'active', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
