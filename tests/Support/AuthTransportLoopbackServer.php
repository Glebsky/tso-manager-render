<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/** Synthetic loopback HTTP only; never resolves or contacts a game host. */
final class AuthTransportLoopbackServer
{
    public readonly string $directory;

    public readonly string $url;

    private mixed $process = null;

    public function __construct()
    {
        $this->directory = sys_get_temp_dir().'/tso-auth-transport-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new RuntimeException('Cannot reserve loopback port.');
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if ($address === false) {
            throw new RuntimeException('Cannot determine loopback address.');
        }
        $this->url = 'http://'.$address;
        $router = dirname(__DIR__).'/Fixtures/AuthTransport/router.php';
        $this->process = proc_open([PHP_BINARY, '-S', $address, $router], [
            0 => ['pipe', 'r'],
            1 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'a'],
            2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'a'],
        ], $pipes, $this->directory);
        if (! is_resource($this->process)) {
            throw new RuntimeException('Cannot start loopback fixture.');
        }
        fclose($pipes[0]);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @stream_socket_client('tcp://'.$address, $errno, $error, 0.05);
            if ($connection !== false) {
                fclose($connection);

                return;
            }
            usleep(20000);
        }
        $this->stop();
        throw new RuntimeException('Loopback fixture did not become ready.');
    }

    /** @return list<array{method: string, path: string}> */
    public function requests(): array
    {
        $file = $this->directory.'/requests.jsonl';
        if (! is_file($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException('Cannot read synthetic request log.');
        }
        $requests = [];
        foreach ($lines as $line) {
            $request = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($request) || ! is_string($request['method'] ?? null) || ! is_string($request['path'] ?? null)) {
                throw new RuntimeException('Invalid synthetic request log entry.');
            }
            $requests[] = ['method' => $request['method'], 'path' => $request['path']];
        }

        return $requests;
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    public function __destruct()
    {
        $this->stop();
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }
}
