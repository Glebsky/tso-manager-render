<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('market_server_connections', function (Blueprint $table) {
            $table->id();
            $table->string('server_id')->unique();
            $table->string('locale', 10)->default('RU');
            $table->string('display_name');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('verification_status', 30)->default('unverified');
            $table->string('sync_status', 30)->default('not_configured');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->onDelete('set null');
        });

        if (! Schema::hasColumn('market_offers', 'server_id')) {
            Schema::table('market_offers', function (Blueprint $table) {
                $table->string('server_id', 50)->default('ru')->after('id');
                $table->index('server_id');
            });
        }

        if (! Schema::hasColumn('market_history', 'server_id')) {
            Schema::table('market_history', function (Blueprint $table) {
                $table->string('server_id', 50)->default('ru')->after('id');
                $table->index(['server_id', 'item_id', 'target_item_id']);
                $table->index(['server_id', 'collected_at']);
            });
        }

        if (! Schema::hasColumn('market_sync_logs', 'server_id')) {
            Schema::table('market_sync_logs', function (Blueprint $table) {
                $table->string('server_id', 50)->nullable()->after('account_id');
                $table->index('server_id');
            });
        }

        // Data migration: seed default server connection and update existing market records
        $marketAccountId = DB::table('settings')->where('key', 'market_account_id')->value('value');
        $accountId = $marketAccountId ? (int) $marketAccountId : null;
        $region = 'ru';

        if ($accountId) {
            $accountRegion = DB::table('accounts')->where('id', $accountId)->value('region');
            if ($accountRegion) {
                $region = strtolower($accountRegion);
            }
        }

        $serverExists = DB::table('market_server_connections')->where('server_id', $region)->exists();
        if (! $serverExists) {
            $locale = strtoupper($region);
            $displayName = strtoupper($region).' Server';

            DB::table('market_server_connections')->insert([
                'server_id' => $region,
                'locale' => $locale,
                'display_name' => $displayName,
                'account_id' => $accountId,
                'verification_status' => $accountId ? 'verified' : 'unverified',
                'sync_status' => $accountId ? 'connected' : 'not_configured',
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Backfill existing rows with the default server_id if needed
        DB::table('market_offers')->whereNull('server_id')->orWhere('server_id', '')->update(['server_id' => $region]);
        DB::table('market_history')->whereNull('server_id')->orWhere('server_id', '')->update(['server_id' => $region]);
        DB::table('market_sync_logs')->whereNull('server_id')->orWhere('server_id', '')->update(['server_id' => $region]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('market_sync_logs', 'server_id')) {
            Schema::table('market_sync_logs', function (Blueprint $table) {
                $table->dropIndex(['server_id']);
                $table->dropColumn('server_id');
            });
        }

        if (Schema::hasColumn('market_history', 'server_id')) {
            Schema::table('market_history', function (Blueprint $table) {
                $table->dropIndex(['server_id', 'item_id', 'target_item_id']);
                $table->dropIndex(['server_id', 'collected_at']);
                $table->dropColumn('server_id');
            });
        }

        if (Schema::hasColumn('market_offers', 'server_id')) {
            Schema::table('market_offers', function (Blueprint $table) {
                $table->dropIndex(['server_id']);
                $table->dropColumn('server_id');
            });
        }

        Schema::dropIfExists('market_server_connections');
    }
};
