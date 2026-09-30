<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Account;
use App\Services\Tasks\BuffPayloadValidator;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Tests\TestCase;

final class BuffPayloadValidatorTest extends TestCase
{
    private BuffPayloadValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BuffPayloadValidator($this->app->make(CacheRepository::class));
    }

    public function test_validates_buff_by_exact_unique_id(): void
    {
        $account = new Account([
            'username' => 'testuser',
            'zone_data' => [
                'availableBuffs' => [
                    [
                        'uniqueId1' => 12345,
                        'uniqueId2' => 0,
                        'buffName_string' => 'ProductivityBuffLvl3',
                        'amount' => 50,
                    ],
                ],
            ],
        ]);

        $payload = [
            'unique_id1' => 12345,
            'unique_id2' => 0,
            'buff_raw_name' => 'ProductivityBuffLvl3',
            'amount' => 1,
            'target_scope' => 'self',
        ];

        $errors = $this->validator->validate($account, $payload);
        $this->assertEmpty($errors);
    }

    public function test_validates_buff_by_fallback_raw_name_when_stack_id_changed(): void
    {
        $account = new Account([
            'username' => 'testuser',
            'zone_data' => [
                'availableBuffs' => [
                    [
                        'uniqueId1' => 99999, // New stack ID in star menu
                        'uniqueId2' => 0,
                        'buffName_string' => 'ProductivityBuffLvl3',
                        'amount' => 100,
                    ],
                ],
            ],
        ]);

        $payload = [
            'unique_id1' => 12345, // Old stack ID from previously saved task
            'unique_id2' => 0,
            'buff_raw_name' => 'ProductivityBuffLvl3',
            'amount' => 1,
            'target_scope' => 'self',
        ];

        $errors = $this->validator->validate($account, $payload);
        $this->assertEmpty($errors);
    }

    public function test_returns_error_when_buff_not_found_by_id_or_name(): void
    {
        $account = new Account([
            'username' => 'testuser',
            'zone_data' => [
                'availableBuffs' => [
                    [
                        'uniqueId1' => 99999,
                        'uniqueId2' => 0,
                        'buffName_string' => 'SomeOtherBuff',
                        'amount' => 10,
                    ],
                ],
            ],
        ]);

        $payload = [
            'unique_id1' => 12345,
            'unique_id2' => 0,
            'buff_raw_name' => 'ProductivityBuffLvl3',
            'amount' => 1,
            'target_scope' => 'self',
        ];

        $errors = $this->validator->validate($account, $payload);
        $this->assertArrayHasKey('payload.unique_id1', $errors);
    }
}
