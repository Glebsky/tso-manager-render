<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->string('schedule_type')->default('daily');
            $table->timestamp('run_at_datetime')->nullable();
            $table->integer('interval_hours')->nullable();
            $table->integer('interval_minutes')->nullable();
        });

        // Use raw SQL to make run_at_time nullable to bypass the Laravel PG identity error
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE scheduled_tasks ALTER COLUMN run_at_time DROP NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->dropColumn(['schedule_type', 'run_at_datetime', 'interval_hours', 'interval_minutes']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE scheduled_tasks ALTER COLUMN run_at_time SET NOT NULL');
        }
    }
};
