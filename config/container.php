<?php

declare(strict_types=1);

use Laminas\ServiceManager\ServiceManager;
use Mezzio\Swoole\Event\ServerStartListener;
use Mezzio\Swoole\Event\WorkerStartListener;

// OpenSwoole has no swoole_set_process_name(), which mezzio-swoole calls on start
if (!function_exists('swoole_set_process_name')) {
    ServerStartListener::$setProcessName = 'cli_set_process_title';
    WorkerStartListener::$setProcessName = 'cli_set_process_title';
}

$config = require __DIR__ . '/config.php';

$dependencies = $config['dependencies'];
$dependencies['services']['config'] = $config;

return new ServiceManager($dependencies);
