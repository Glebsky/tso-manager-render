<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('accounts')->orderBy('id')->chunk(100, function ($accounts): void {
            foreach ($accounts as $account) {
                $updates = [];
                foreach (['password', 'dso_auth_user', 'dso_auth_token'] as $field) {
                    $val = $account->$field;
                    if ($val === null || $val === '') {
                        continue;
                    }

                    try {
                        Crypt::decryptString((string) $val);
                        // Value is already validly encrypted
                    } catch (Throwable $e) {
                        try {
                            $updates[$field] = Crypt::encryptString((string) $val);
                        } catch (Throwable $ex) {
                            $updates[$field] = null;
                        }
                    }
                }

                if (! empty($updates)) {
                    DB::table('accounts')->where('id', $account->id)->update($updates);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('accounts')->orderBy('id')->chunk(100, function ($accounts): void {
            foreach ($accounts as $account) {
                $updates = [];
                foreach (['password', 'dso_auth_user', 'dso_auth_token'] as $field) {
                    $val = $account->$field;
                    if ($val === null || $val === '') {
                        continue;
                    }

                    try {
                        $updates[$field] = Crypt::decryptString((string) $val);
                    } catch (Throwable $e) {
                        // Already plaintext or invalid payload, leave unchanged
                    }
                }

                if (! empty($updates)) {
                    DB::table('accounts')->where('id', $account->id)->update($updates);
                }
            }
        });
    }
};
