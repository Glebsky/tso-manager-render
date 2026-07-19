<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if (str_contains(get_class($this), 'Tests\\Feature')) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
                    $user = \App\Models\User::factory()->create();
                    $this->actingAs($user);
                }
            } catch (\Throwable $e) {
                // Ignore database issues for tests that do not use migrations/database
            }
        }
    }
}
