<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FixPostgresSequencesTest extends TestCase
{
    public function test_command_handles_non_postgres_gracefully(): void
    {
        $exitCode = Artisan::call('tso:fix-sequences');

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Sequence resynchronization is only applicable to PostgreSQL', $output);
    }
}
