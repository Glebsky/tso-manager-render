<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Liveness timestamp of the process that owns the task run (FR-007).
     * Kept separate from `queued_at`, which records when the run was reserved.
     * Additive: existing rows start with NULL and fall back to `queued_at`
     * in stale recovery.
     */
    public function up(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->timestamp('heartbeat_at')->nullable()->after('queued_at');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->dropColumn('heartbeat_at');
        });
    }
};
