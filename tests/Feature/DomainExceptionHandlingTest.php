<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\AccountSyncException;
use App\Exceptions\FriendNotFoundException;
use App\Exceptions\InvalidTaskTypeException;
use App\Exceptions\MarketOperationException;
use App\Exceptions\TaskInactiveException;
use Exception;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DomainExceptionHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/test-domain-exception', function () {
            throw new AccountSyncException('Custom sync failure message', 500);
        });

        Route::get('/api/test-task-inactive', function () {
            throw new TaskInactiveException(42);
        });

        Route::get('/api/test-market-operation', function () {
            throw MarketOperationException::unprocessable('Market offer invalid');
        });

        Route::get('/api/test-friend-not-found', function () {
            throw new FriendNotFoundException;
        });

        Route::get('/api/test-invalid-task-type', function () {
            throw new InvalidTaskTypeException('Task #1 is not a sequence task.', 422);
        });

        Route::get('/api/test-unhandled-exception', function () {
            throw new Exception('Sensitive database connection string leaked!');
        });
    }

    public function test_account_sync_exception_renders_declared_json(): void
    {
        $response = $this->getJson('/api/test-domain-exception');

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'Custom sync failure message',
                'code' => 500,
            ]);
    }

    public function test_task_inactive_exception_renders_422_json(): void
    {
        $response = $this->getJson('/api/test-task-inactive');

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'code']);
    }

    public function test_market_operation_exception_renders_422_json(): void
    {
        $response = $this->getJson('/api/test-market-operation');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Market offer invalid',
            ]);
    }

    public function test_friend_not_found_exception_renders_404_json(): void
    {
        $response = $this->getJson('/api/test-friend-not-found');

        $response->assertStatus(404)
            ->assertJsonStructure(['message', 'code']);
    }

    public function test_invalid_task_type_exception_renders_422_json(): void
    {
        $response = $this->getJson('/api/test-invalid-task-type');

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Task #1 is not a sequence task.',
                'code' => 422,
            ]);
    }

    public function test_unhandled_exception_redacts_sensitive_details(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/test-unhandled-exception');

        $response->assertStatus(500);
        $this->assertStringNotContainsString('Sensitive database connection', $response->getContent() ?: '');
    }
}
