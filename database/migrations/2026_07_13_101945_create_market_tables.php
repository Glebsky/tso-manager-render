<?php

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
        Schema::create('market_offers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('offer_id')->unique();
            $table->bigInteger('player_id');
            $table->string('sender_name');
            $table->string('item_id', 100);
            $table->string('item_name', 255);
            $table->integer('amount');
            $table->string('target_item_id', 100);
            $table->string('target_item_name', 255);
            $table->integer('target_amount');
            $table->double('price');
            $table->integer('volume');
            $table->integer('lots_remaining');
            $table->timestamp('created_at');
            $table->timestamp('collected_at');
        });

        Schema::create('market_history', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('offer_id');
            $table->bigInteger('player_id');
            $table->string('item_id', 100);
            $table->string('item_name', 255);
            $table->integer('amount');
            $table->string('target_item_id', 100);
            $table->string('target_item_name', 255);
            $table->integer('target_amount');
            $table->double('price');
            $table->integer('volume');
            $table->timestamp('collected_at');

            $table->index(['item_id', 'target_item_id']);
            $table->index('collected_at');
        });

        Schema::create('market_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('account_id')->nullable();
            $table->string('action');
            $table->string('status', 50);
            $table->text('message');
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_sync_logs');
        Schema::dropIfExists('market_history');
        Schema::dropIfExists('market_offers');
    }
};
