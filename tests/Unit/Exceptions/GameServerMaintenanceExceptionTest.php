<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\Contracts\HasApiPresentation;
use App\Exceptions\GameServerMaintenanceException;
use App\Exceptions\TaskExecutionException;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class GameServerMaintenanceExceptionTest extends TestCase
{
    public function test_it_extends_task_execution_exception_and_implements_has_api_presentation(): void
    {
        $ex = new GameServerMaintenanceException;

        $this->assertInstanceOf(TaskExecutionException::class, $ex);
        $this->assertInstanceOf(HasApiPresentation::class, $ex);
        $this->assertSame(503, $ex->httpStatus());
    }

    public function test_it_localizes_default_maintenance_message_in_supported_locales(): void
    {
        App::setLocale('ru');
        $ruEx = new GameServerMaintenanceException;
        $this->assertSame('Сервер игры на обслуживании.', $ruEx->getMessage());

        App::setLocale('en');
        $enEx = new GameServerMaintenanceException;
        $this->assertSame('Game server is under maintenance.', $enEx->getMessage());

        App::setLocale('uk');
        $ukEx = new GameServerMaintenanceException;
        $this->assertSame('Сервер гри на обслуговуванні.', $ukEx->getMessage());
    }

    public function test_it_localizes_queue_information_properly(): void
    {
        App::setLocale('ru');
        $ruEx = GameServerMaintenanceException::fromResponse(202, 'queuePos=2&queueSize=2');
        $this->assertSame('Сервер игры на обслуживании (очередь: 2/2).', $ruEx->getMessage());

        App::setLocale('en');
        $enEx = GameServerMaintenanceException::fromResponse(202, 'queuePos=2&queueSize=2');
        $this->assertSame('Game server is under maintenance (queue: 2/2).', $enEx->getMessage());

        App::setLocale('uk');
        $ukEx = GameServerMaintenanceException::fromResponse(202, 'queuePos=2&queueSize=2');
        $this->assertSame('Сервер гри на обслуговуванні (черга: 2/2).', $ukEx->getMessage());
    }

    public function test_it_localizes_custom_details_properly(): void
    {
        App::setLocale('ru');
        $ruEx = GameServerMaintenanceException::fromResponse(503, 'Service Unavailable');
        $this->assertSame('Сервер игры на обслуживании (Service Unavailable).', $ruEx->getMessage());

        $ruExStatusOnly = GameServerMaintenanceException::fromResponse(503, '');
        $this->assertSame('Сервер игры на обслуживании (HTTP 503).', $ruExStatusOnly->getMessage());
    }

    public function test_to_payload_contains_structured_information(): void
    {
        App::setLocale('ru');
        $ex = GameServerMaintenanceException::fromResponse(202, 'queuePos=2&queueSize=2');
        $payload = $ex->toPayload();

        $this->assertSame('tasks.error.server_maintenance_queue', $payload['key']);
        $this->assertSame(['pos' => 2, 'size' => 2], $payload['params']);
        $this->assertSame('Сервер игры на обслуживании (очередь: 2/2).', $payload['message']);
    }
}
