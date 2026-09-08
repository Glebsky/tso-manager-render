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
        if (Schema::hasTable('bot_logs')) {
            Schema::table('bot_logs', function (Blueprint $table) {
                $table->index(['created_at', 'id'], 'idx_bot_logs_created_at_id');
            });
        }

        if (Schema::hasTable('market_history')) {
            Schema::table('market_history', function (Blueprint $table) {
                $table->index(['server_id', 'offer_id'], 'idx_market_history_server_offer');
            });
        }

        if (Schema::hasTable('market_offers')) {
            Schema::table('market_offers', function (Blueprint $table) {
                $table->index(['server_id', 'collected_at'], 'idx_market_offers_server_collected');
            });
        }

        if (Schema::hasTable('market_sync_logs')) {
            Schema::table('market_sync_logs', function (Blueprint $table) {
                $table->index(['server_id', 'id'], 'idx_market_sync_logs_server_id_id');
                $table->index(['status', 'created_at'], 'idx_market_sync_logs_status_created');
                $table->index('created_at', 'idx_market_sync_logs_created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('market_sync_logs')) {
            Schema::table('market_sync_logs', function (Blueprint $table) {
                $table->dropIndex('idx_market_sync_logs_created_at');
                $table->dropIndex('idx_market_sync_logs_status_created');
                $table->dropIndex('idx_market_sync_logs_server_id_id');
            });
        }

        if (Schema::hasTable('market_offers')) {
            Schema::table('market_offers', function (Blueprint $table) {
                $table->dropIndex('idx_market_offers_server_collected');
            });
        }

        if (Schema::hasTable('market_history')) {
            Schema::table('market_history', function (Blueprint $table) {
                $table->dropIndex('idx_market_history_server_offer');
            });
        }

        if (Schema::hasTable('bot_logs')) {
            Schema::table('bot_logs', function (Blueprint $table) {
                $table->dropIndex('idx_bot_logs_created_at_id');
            });
        }
    }
};
