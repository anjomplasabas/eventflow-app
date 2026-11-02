<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_minor');
            $table->unsignedInteger('line_total_minor');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['receipt_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_items');
    }
};
