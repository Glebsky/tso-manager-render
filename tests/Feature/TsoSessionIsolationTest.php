<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\Amf\Transport\HttpTsoClient;
use App\Services\TaskExecutionService;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class TsoSessionIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @var mixed */
    private $authMock;

    /** @var mixed */
    private $amfMock;

    /** @var mixed */
    private $parserMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authMock = Mockery::mock(TsoAuthService::class);
        $this->amfMock = Mockery::mock(TsoAmfService::class);
        $this->parserMock = Mockery::mock(ZoneParserService::class);

        $this->app->instance(TsoAuthService::class, $this->authMock);
        $this->app->instance(TsoAmfService::class, $this->amfMock);
        $this->app->instance(ZoneParserService::class, $this->parserMock);
    }

    public function test_two_accounts_maintain_isolated_dsids_and_sessions(): void
    {
        $account1 = Account::create([
            'username' => 'user_one',
            'password' => 'secret1',
            'region' => 'ru',
            'nickname' => 'UserOne',
            'dso_auth_user' => '1001',
            'dso_auth_token' => 'token_user_one',
            'bb_url' => 'https://r02-ls.thesettlersonline.ru/',
        ]);

        $account2 = Account::create([
            'username' => 'user_two',
            'password' => 'secret2',
            'region' => 'ru',
            'nickname' => 'UserTwo',
            'dso_auth_user' => '1002',
            'dso_auth_token' => 'token_user_two',
            'bb_url' => 'https://r02-ls.thesettlersonline.ru/',
        ]);

        /** @var HttpTsoClient $client */
        $client = $this->app->make(HttpTsoClient::class);
        $client->setDsId('dsid-account-1', (int) $account1->id);
        $client->setDsId('dsid-account-2', (int) $account2->id);

        // Invalidate session for account 1 only
        $client->invalidateSession((int) $account1->id);

        // Account 1 gen should be 2, account 2 gen should remain 1
        $this->assertEquals(2, Cache::get("tso:amf_gen:{$account1->id}"));
        $this->assertEquals(1, (int) Cache::get("tso:amf_gen:{$account2->id}", 1));
    }

    public function test_task_execution_retries_1012_with_zone_warmup_and_succeeds(): void
    {
        $account = Account::create([
            'username' => 'warmup_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'warmup_user',
            'dso_auth_user' => '2001',
            'dso_auth_token' => 'token_warmup',
            'bb_url' => 'https://r02-ls.thesettlersonline.ru/',
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => TaskType::SendGeologist,
            'payload' => ['task_type' => 1, 'sub_task_id' => 0, 'unique_id1' => 10, 'unique_id2' => 20],
            'schedule_type' => ScheduleType::Daily,
            'is_active' => true,
            'status' => TaskStatus::Pending,
        ]);

        $this->authMock->shouldReceive('isAuthenticated')->andReturn(true);

        $this->amfMock->shouldReceive('sendSpecialist')
            ->twice()
            ->andReturnUsing(function () {
                static $attempts = 0;
                $attempts++;
                if ($attempts === 1) {
                    throw new GameServerErrorException(1012, 'Newer game session detected');
                }

                return 'geo_success_response';
            });

        $this->amfMock->shouldReceive('resetClient')->with((int) $account->id)->once();
        $this->amfMock->shouldReceive('ensureZoneLoaded')->with(Mockery::any(), Mockery::any(), Mockery::any())->once()->andReturn('zone_data');

        $service = $this->app->make(TaskExecutionService::class);
        $result = $service->execute($task);

        $this->assertEquals('geo_success_response', $result);
        $task->refresh();
        $this->assertEquals(TaskStatus::Completed, $task->status);
    }
}
