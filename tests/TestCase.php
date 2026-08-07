<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if (str_contains(get_class($this), 'Tests\\Feature')) {
            try {
                if (Schema::hasTable('users')) {
                    $user = User::factory()->create();
                    $this->actingAs($user);
                }
            } catch (\Throwable $e) {
                // Ignore database issues for tests that do not use migrations/database
            }
        }
    }
}
