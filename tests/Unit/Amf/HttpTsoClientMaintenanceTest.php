<?php

declare(strict_types=1);

namespace Tests\Unit\Amf;

use App\Exceptions\GameServerMaintenanceException;
use App\Services\Amf\Transport\HttpTsoClient;
use ReflectionMethod;
use Tests\TestCase;

class HttpTsoClientMaintenanceTest extends TestCase
{
    public function test_is_maintenance_response_identifies_maintenance_status_and_keywords(): void
    {
        $client = app(HttpTsoClient::class);
        $method = new ReflectionMethod($client, 'isMaintenanceResponse');

        $this->assertTrue($method->invoke($client, 503, ''));
        $this->assertTrue($method->invoke($client, 200, 'Server maintenance in progress'));
        $this->assertTrue($method->invoke($client, 200, 'Wartungsarbeiten'));
        $this->assertTrue($method->invoke($client, 200, 'Техническое обслуживание'));

        $this->assertFalse($method->invoke($client, 200, 'http://game-01.thesettlersonline.com/amf'));
        $this->assertFalse($method->invoke($client, 202, 'queuePos=1&queueSize=2'));
    }

    public function test_from_response_formats_queue_pos_and_size(): void
    {
        $ex = GameServerMaintenanceException::fromResponse(202, 'queuePos=2&queueSize=2');
        $this->assertInstanceOf(GameServerMaintenanceException::class, $ex);
        $this->assertSame(503, $ex->httpStatus());
        $payload = $ex->toPayload();
        $this->assertSame('tasks.error.server_maintenance_queue', $payload['key']);
        $this->assertSame(2, $payload['params']['pos']);
        $this->assertSame(2, $payload['params']['size']);
    }
}
