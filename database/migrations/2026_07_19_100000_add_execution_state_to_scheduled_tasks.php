<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('is_active');
            $table->timestamp('queued_at')->nullable()->after('status');
            $table->string('execution_token')->nullable()->after('queued_at');
            $table->integer('completed_steps')->default(0)->after('execution_token');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->dropColumn(['status', 'queued_at', 'execution_token', 'completed_steps']);
        });
    }
};
