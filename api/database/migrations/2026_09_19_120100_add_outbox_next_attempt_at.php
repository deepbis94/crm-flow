<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_events', function (Blueprint $table) {
            $table->timestamp('next_attempt_at')->nullable()->after('attempts');
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::table('outbox_events', function (Blueprint $table) {
            $table->dropIndex(['status', 'next_attempt_at']);
            $table->dropColumn('next_attempt_at');
        });
    }
};
