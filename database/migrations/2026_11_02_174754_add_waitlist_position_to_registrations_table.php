<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->unsignedInteger('waitlist_position')->nullable()->after('waitlisted_at');
            $table->index(['ticket_type_id', 'status', 'waitlist_position']);
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['ticket_type_id', 'status', 'waitlist_position']);
            $table->dropColumn('waitlist_position');
        });
    }
};
