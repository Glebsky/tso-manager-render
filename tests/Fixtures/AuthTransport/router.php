<?php

declare(strict_types=1);

// No request bodies, credentials or external destinations are recorded.
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
file_put_contents(getcwd().'/requests.jsonl', json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => $path,
])."\n", FILE_APPEND | LOCK_EX);

if (preg_match('~^/redirect/(\d+)/~', $path, $matches)) {
    header('Location: /redirect-target', true, (int) $matches[1]);
    echo 'synthetic redirect';
} elseif (preg_match('~^/outage/(\d+)/~', $path, $matches)) {
    http_response_code((int) $matches[1]);
    echo 'Cloudflare connection timed out';
} elseif (preg_match('~^/status/(\d+)/~', $path, $matches)) {
    http_response_code((int) $matches[1]);
    echo 'ERROR synthetic rejection or outage';
} elseif (str_starts_with($path, '/ls-outage/')) {
    if (str_ends_with($path, '/authenticate')) {
        echo 'tok|nick|hash';
    } else {
        http_response_code(522);
        echo 'Cloudflare connection timed out';
    }
} elseif (str_starts_with($path, '/ls-queue/')) {
    if (str_ends_with($path, '/authenticate')) {
        echo 'tok|nick|hash';
    } else {
        http_response_code(202);
        echo 'queuePos=1&queueSize=2';
    }
} elseif (str_starts_with($path, '/timeout/')) {
    sleep(3);
    echo 'late synthetic success';
} elseif (str_starts_with($path, '/rejected/')) {
    echo 'ERROR synthetic rejected token';
} else {
    echo 'synthetic success';
}
