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
        if (Schema::hasTable('market_offers')) {
            Schema::table('market_offers', function (Blueprint $table) {
                $table->index(['server_id', 'created_at'], 'idx_market_offers_server_created');
                $table->index(['server_id', 'item_id', 'target_item_id', 'created_at'], 'idx_market_offers_server_pair_created');
            });
        }

        if (Schema::hasTable('market_history')) {
            Schema::table('market_history', function (Blueprint $table) {
                $table->index(['server_id', 'item_id', 'target_item_id', 'collected_at'], 'idx_market_history_server_pair_collected');
            });
        }

        if (Schema::hasTable('scheduled_tasks')) {
            Schema::table('scheduled_tasks', function (Blueprint $table) {
                $table->index(['is_active', 'status', 'schedule_type'], 'idx_scheduled_tasks_active_status_type');
            });
        }

        if (Schema::hasTable('bot_logs')) {
            Schema::table('bot_logs', function (Blueprint $table) {
                $table->index(['account_id', 'level', 'created_at'], 'idx_bot_logs_account_level_created');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('bot_logs')) {
            Schema::table('bot_logs', function (Blueprint $table) {
                $table->dropIndex('idx_bot_logs_account_level_created');
            });
        }

        if (Schema::hasTable('scheduled_tasks')) {
            Schema::table('scheduled_tasks', function (Blueprint $table) {
                $table->dropIndex('idx_scheduled_tasks_active_status_type');
            });
        }

        if (Schema::hasTable('market_history')) {
            Schema::table('market_history', function (Blueprint $table) {
                $table->dropIndex('idx_market_history_server_pair_collected');
            });
        }

        if (Schema::hasTable('market_offers')) {
            Schema::table('market_offers', function (Blueprint $table) {
                $table->dropIndex('idx_market_offers_server_pair_created');
                $table->dropIndex('idx_market_offers_server_created');
            });
        }
    }
};
