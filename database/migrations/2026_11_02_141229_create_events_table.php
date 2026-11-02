<?php

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('status', 16)->default(EventStatus::Draft->value);
            $table->string('visibility', 16)->default(EventVisibility::Public->value);
            $table->string('access_code')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 64);
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('per_attendee_limit')->default(1);
            $table->text('cancellation_policy')->nullable();
            $table->text('attendee_instructions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organizer_id', 'slug']);
            $table->index(['organizer_id', 'status', 'starts_at']);
            $table->index(['visibility', 'status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
