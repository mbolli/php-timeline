<?php

declare(strict_types=1);

use App\Infrastructure\Http\Listener\SseRequestListener;
use Laminas\Stdlib\ArrayUtils\MergeReplaceKey;
use Mezzio\Swoole\Event\HotCodeReloaderWorkerStartListener;
use Mezzio\Swoole\Event\RequestEvent;
use Mezzio\Swoole\Event\RequestHandlerRequestListener;
use Mezzio\Swoole\Event\StaticResourceRequestListener;
use Mezzio\Swoole\Event\WorkerStartEvent;

return [
    'debug' => false,
    'database' => [
        'dsn' => 'sqlite:' . realpath(__DIR__ . '/../../data') . '/timeline.db',
    ],
    'templates' => [
        'paths' => [
            'app' => [realpath(__DIR__ . '/../../templates')],
        ],
    ],
    'hot-code-reload' => [
        // Time in milliseconds between checks to changes in files.
        'interval' => 500,
        'paths' => [
            // List of paths, either files or directories, to scan for changes.
            // By default this is empty; you will need to configure it.
            // A common value:
            getcwd(),
        ],
    ],
    'mezzio-swoole' => [
        'swoole-http-server' => [
            'host' => '127.0.0.1',
            'port' => 8080,
            'options' => [
                'worker_num'      => 1,          // The number of HTTP Server Workers
                'enable_coroutine' => true,
                'pid_file' => realpath(__DIR__ . '/../../data') . '/swoole.pid',
            ],
            'static-files' => [
                'enable' => true,
                'document-root' => realpath(__DIR__ . '/../../public'),
                'type-map' => [
                    'css' => 'text/css',
                    'js' => 'application/javascript',
                    'map' => 'application/json',
                ],
                'directives' => [
                    '/\.(css|js|map)$/' => [
                        'cache-control' => ['public', 'max-age=31536000'],
                        'last-modified' => true,
                        'etag' => true,
                    ],
                ],
            ],
            'listeners' => [
                // SSE listener MUST run before RequestHandlerRequestListener
                // to intercept /updates and handle SSE streaming.
                // Using MergeReplaceKey to override the default listener order.
                RequestEvent::class => new MergeReplaceKey([
                    StaticResourceRequestListener::class,
                    SseRequestListener::class,
                    RequestHandlerRequestListener::class,
                ]),
                // Register the hot code reloader listener with the WorkerStartEvent
                WorkerStartEvent::class => [
                    HotCodeReloaderWorkerStartListener::class,
                ],
            ],
        ],
    ],
];
