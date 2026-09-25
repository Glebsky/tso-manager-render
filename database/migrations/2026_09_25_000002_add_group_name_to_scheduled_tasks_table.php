<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table): void {
            $table->string('group_name', 120)->nullable()->after('name');
            $table->index(['account_id', 'group_name'], 'idx_scheduled_tasks_account_group');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table): void {
            $table->dropIndex('idx_scheduled_tasks_account_group');
            $table->dropColumn('group_name');
        });
    }
};
