<?php

$appStorage = '/tmp/storage';

if (!is_dir($appStorage)) {
    mkdir($appStorage . '/framework/views', 0755, true);
    mkdir($appStorage . '/framework/cache/data', 0755, true);
    mkdir($appStorage . '/framework/sessions', 0755, true);
    mkdir($appStorage . '/framework/testing', 0755, true);
    mkdir($appStorage . '/logs', 0755, true);
}

putenv("APP_STORAGE={$appStorage}");
putenv("VIEW_COMPILED_PATH={$appStorage}/framework/views");
putenv("CACHE_STORE=array");
putenv("CACHE_DRIVER=array");
putenv("APP_ROUTES_CACHE={$appStorage}/routes.php");
putenv("APP_CONFIG_CACHE={$appStorage}/config.php");

require __DIR__ . '/../public/index.php';
