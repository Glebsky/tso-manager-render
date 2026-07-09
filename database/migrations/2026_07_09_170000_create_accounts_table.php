<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('username');
            $table->text('password');
            $table->string('region')->default('ru');
            $table->string('nickname')->nullable();
            $table->string('dso_auth_user')->nullable();
            $table->string('dso_auth_token')->nullable();
            $table->string('bb_url')->nullable();
            $table->string('status')->default('offline');
            $table->text('zone_data')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
