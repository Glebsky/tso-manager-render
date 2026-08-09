<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BotLog;
use App\Services\Account\Sync\AccountSyncLogger;
use App\Services\Tasks\TaskActivityLogger;
use App\Support\Security\CredentialRedactor;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CredentialEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_db_column_value_is_encrypted_and_does_not_match_plaintext(): void
    {
        $plainPassword = 'SecretPassword123!';
        $plainDsoUser = '987654321';
        $plainDsoToken = 'abc-123-dso-auth-token-xyz';

        $account = Account::create([
            'username' => 'testuser',
            'password' => $plainPassword,
            'dso_auth_user' => $plainDsoUser,
            'dso_auth_token' => $plainDsoToken,
            'region' => 'de',
        ]);

        $rawRow = DB::table('accounts')->where('id', $account->id)->first();

        $this->assertNotNull($rawRow);
        $this->assertNotEquals($plainPassword, $rawRow->password);
        $this->assertNotEquals($plainDsoUser, $rawRow->dso_auth_user);
        $this->assertNotEquals($plainDsoToken, $rawRow->dso_auth_token);

        $this->assertEquals($plainPassword, Crypt::decryptString((string) $rawRow->password));
        $this->assertEquals($plainDsoUser, Crypt::decryptString((string) $rawRow->dso_auth_user));
        $this->assertEquals($plainDsoToken, Crypt::decryptString((string) $rawRow->dso_auth_token));
    }

    public function test_model_attribute_access_decrypts_correctly_back_to_plaintext(): void
    {
        $plainPassword = 'MySecretGamePassword!@#';
        $plainDsoUser = '11223344';
        $plainDsoToken = 'session-token-998877';

        $account = Account::create([
            'username' => 'player1',
            'password' => $plainPassword,
            'dso_auth_user' => $plainDsoUser,
            'dso_auth_token' => $plainDsoToken,
            'region' => 'en',
        ]);

        $fetchedAccount = Account::findOrFail($account->id);

        $this->assertEquals($plainPassword, $fetchedAccount->password);
        $this->assertEquals($plainDsoUser, $fetchedAccount->dso_auth_user);
        $this->assertEquals($plainDsoToken, $fetchedAccount->dso_auth_token);
    }

    public function test_loggers_redact_passwords_and_tokens_when_logging_account_operations(): void
    {
        $plainPassword = 'SuperSecretPassword456';
        $plainToken = 'secret-dso-token-777';

        $account = Account::create([
            'username' => 'logged_user',
            'password' => $plainPassword,
            'dso_auth_user' => '55555',
            'dso_auth_token' => $plainToken,
            'region' => 'us',
        ]);

        $syncLogger = new AccountSyncLogger;
        $syncLogger->logFailure($account, new Exception("Sync failed for password {$plainPassword} with token {$plainToken}"));

        $lastBotLog = BotLog::where('account_id', $account->id)->latest('id')->first();
        $this->assertNotNull($lastBotLog);
        $this->assertStringNotContainsString($plainPassword, $lastBotLog->message);
        $this->assertStringNotContainsString($plainToken, $lastBotLog->message);
        $this->assertStringContainsString('[REDACTED]', $lastBotLog->message);

        $taskLogger = new TaskActivityLogger;
        $taskLogger->deleted($account->id, 99, "Task deleted with password={$plainPassword}");

        $taskBotLog = BotLog::where('account_id', $account->id)->latest('id')->first();
        $this->assertNotNull($taskBotLog);
        $this->assertStringNotContainsString($plainPassword, $taskBotLog->message);
        $this->assertStringContainsString('[REDACTED]', $taskBotLog->message);

        $rawLogMsg = "Failed login for password={$plainPassword}&token={$plainToken}";
        $redactedMsg = CredentialRedactor::redact($rawLogMsg, $account);
        $this->assertStringNotContainsString($plainPassword, $redactedMsg);
        $this->assertStringNotContainsString($plainToken, $redactedMsg);
    }

    public function test_migration_encrypts_existing_plaintext_credentials_and_down_decrypts(): void
    {
        $rawId = DB::table('accounts')->insertGetId([
            'username' => 'legacy_user',
            'password' => 'PlaintextPass123',
            'dso_auth_user' => '12345678',
            'dso_auth_token' => 'LegacyToken999',
            'region' => 'de',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = include database_path('migrations/2026_08_09_120000_encrypt_account_credentials.php');

        $migration->up();

        $rawRowUp = DB::table('accounts')->where('id', $rawId)->first();
        $this->assertNotNull($rawRowUp);
        $this->assertNotEquals('PlaintextPass123', $rawRowUp->password);
        $this->assertNotEquals('12345678', $rawRowUp->dso_auth_user);
        $this->assertNotEquals('LegacyToken999', $rawRowUp->dso_auth_token);

        $this->assertEquals('PlaintextPass123', Crypt::decryptString((string) $rawRowUp->password));
        $this->assertEquals('12345678', Crypt::decryptString((string) $rawRowUp->dso_auth_user));
        $this->assertEquals('LegacyToken999', Crypt::decryptString((string) $rawRowUp->dso_auth_token));

        $migration->down();

        $rawRowDown = DB::table('accounts')->where('id', $rawId)->first();
        $this->assertNotNull($rawRowDown);
        $this->assertEquals('PlaintextPass123', $rawRowDown->password);
        $this->assertEquals('12345678', $rawRowDown->dso_auth_user);
        $this->assertEquals('LegacyToken999', $rawRowDown->dso_auth_token);
    }
}
