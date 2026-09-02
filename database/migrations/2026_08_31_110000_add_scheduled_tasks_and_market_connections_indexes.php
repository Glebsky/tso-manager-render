<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('scheduled_tasks')) {
            Schema::table('scheduled_tasks', function (Blueprint $table) {
                $table->index('created_at', 'idx_scheduled_tasks_created_at');
                $table->index('account_id', 'idx_scheduled_tasks_account_id');
            });
        }

        if (Schema::hasTable('market_server_connections')) {
            Schema::table('market_server_connections', function (Blueprint $table) {
                $table->index('account_id', 'idx_market_server_connections_account_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('market_server_connections')) {
            Schema::table('market_server_connections', function (Blueprint $table) {
                $table->dropIndex('idx_market_server_connections_account_id');
            });
        }

        if (Schema::hasTable('scheduled_tasks')) {
            Schema::table('scheduled_tasks', function (Blueprint $table) {
                $table->dropIndex('idx_scheduled_tasks_account_id');
                $table->dropIndex('idx_scheduled_tasks_created_at');
            });
        }
    }
};
