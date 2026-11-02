<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('waitlist_enabled')->default(false)->after('per_attendee_limit');
            $table->unsignedInteger('waitlist_capacity')->nullable()->after('waitlist_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['waitlist_enabled', 'waitlist_capacity']);
        });
    }
};
