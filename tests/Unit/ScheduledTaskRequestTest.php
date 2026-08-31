<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\Tasks\StoreScheduledTaskRequest;
use App\Http\Requests\Tasks\UpdateScheduledTaskRequest;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ScheduledTaskRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_request_requires_building_grid_for_stop_production(): void
    {
        $account = Account::create([
            'username' => 'gridtest',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'gridtest',
        ]);

        $data = [
            'account_id' => $account->id,
            'task_type' => 'stop_production',
            'payload' => [],
            'schedule_type' => 'daily',
            'run_at_time' => '10:00',
        ];

        $request = StoreScheduledTaskRequest::create('/api/tasks', 'POST', $data);
        $request->setContainer($this->app);

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payload.grid', $validator->errors()->toArray());
    }

    public function test_store_request_passes_valid_building_task(): void
    {
        $account = Account::create([
            'username' => 'gridpass',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'gridpass',
        ]);

        $data = [
            'account_id' => $account->id,
            'task_type' => 'stop_production',
            'payload' => ['grid' => 150],
            'schedule_type' => 'daily',
            'run_at_time' => '10:00',
        ];

        $request = StoreScheduledTaskRequest::create('/api/tasks', 'POST', $data);
        $request->setContainer($this->app);

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_store_request_validates_specialist_task(): void
    {
        $account = Account::create([
            'username' => 'spectest',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'spectest',
        ]);

        $data = [
            'account_id' => $account->id,
            'task_type' => 'send_geologist',
            'payload' => [
                'task_type' => 1,
                'sub_task_id' => 2,
                'unique_id1' => 10,
                'unique_id2' => 20,
            ],
            'schedule_type' => 'daily',
            'run_at_time' => '12:00',
        ];

        $request = StoreScheduledTaskRequest::create('/api/tasks', 'POST', $data);
        $request->setContainer($this->app);

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_update_request_allows_partial_updates(): void
    {
        $data = [
            'name' => 'Updated Task Name Only',
        ];

        $request = UpdateScheduledTaskRequest::create('/api/tasks/1', 'PUT', $data);
        $request->setContainer($this->app);

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_store_request_validates_build_mine_task(): void
    {
        $account = Account::create([
            'username' => 'minetest',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'minetest',
        ]);

        $data = [
            'account_id' => $account->id,
            'task_type' => 'build_mine',
            'payload' => ['grid' => 6431, 'deposit_name' => 'IronOre'],
            'schedule_type' => 'daily',
            'run_at_time' => '10:00',
        ];

        $request = StoreScheduledTaskRequest::create('/api/tasks', 'POST', $data);
        $request->setContainer($this->app);

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_store_request_validates_upgrade_mine_task(): void
    {
        $account = Account::create([
            'username' => 'upgradetest',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'upgradetest',
        ]);

        $data = [
            'account_id' => $account->id,
            'task_type' => 'upgrade_mine',
            'payload' => ['grid' => 6431, 'building_name' => 'IronMine', 'max_level' => 5],
            'schedule_type' => 'daily',
            'run_at_time' => '10:00',
        ];

        $request = StoreScheduledTaskRequest::create('/api/tasks', 'POST', $data);
        $request->setContainer($this->app);

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->fails());
    }
}
