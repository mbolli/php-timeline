<?php

declare(strict_types=1);

use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use Mezzio\ConfigProvider;

$cacheConfig = [
    'config_cache_path' => 'data/cache/config-cache.php',
];

// Environment overrides win over every config file, so the e2e tests can run their own server and database
$envConfig = [];
if (($envPort = getenv('TIMELINE_PORT')) !== false && $envPort !== '') {
    $envConfig['mezzio-swoole']['swoole-http-server']['port'] = (int) $envPort;
}
if (($envPidFile = getenv('TIMELINE_PID_FILE')) !== false && $envPidFile !== '') {
    $envConfig['mezzio-swoole']['swoole-http-server']['options']['pid_file'] = $envPidFile;
}
if (($envDb = getenv('TIMELINE_DB')) !== false && $envDb !== '') {
    $envConfig['database']['dsn'] = 'sqlite:' . $envDb;
}

$aggregator = new ConfigAggregator([
    Mezzio\Router\ConfigProvider::class,
    Mezzio\Router\FastRouteRouter\ConfigProvider::class,
    Laminas\Diactoros\ConfigProvider::class,
    Mezzio\Helper\ConfigProvider::class,

    ConfigProvider::class,
    App\ConfigProvider::class,
    Mezzio\Swoole\ConfigProvider::class,

    new ArrayProvider($cacheConfig),
    new PhpFileProvider(realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
    new ArrayProvider($envConfig),
], $cacheConfig['config_cache_path']);

return $aggregator->getMergedConfig();
