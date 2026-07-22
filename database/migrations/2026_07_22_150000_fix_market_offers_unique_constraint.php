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
            try {
                Schema::table('market_offers', function (Blueprint $table) {
                    $table->dropUnique(['offer_id']);
                });
            } catch (\Throwable $e) {
                // Index might already be dropped or named differently
            }

            try {
                Schema::table('market_offers', function (Blueprint $table) {
                    $table->unique(['server_id', 'offer_id']);
                });
            } catch (\Throwable $e) {
                // Index might already exist
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('market_offers')) {
            try {
                Schema::table('market_offers', function (Blueprint $table) {
                    $table->dropUnique(['server_id', 'offer_id']);
                });
            } catch (\Throwable $e) {
                // Ignore
            }

            try {
                Schema::table('market_offers', function (Blueprint $table) {
                    $table->unique(['offer_id']);
                });
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }
};
