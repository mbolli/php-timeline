<?php

declare(strict_types=1);

use App\Infrastructure\Http\Handler\Command\GroupCommandHandler;
use App\Infrastructure\Http\Handler\Command\ItemCommandHandler;
use App\Infrastructure\Http\Handler\HomeHandler;
use App\Infrastructure\Http\Handler\Query\ItemQueryHandler;
use App\Infrastructure\Http\Handler\Query\TimelineQueryHandler;
use App\Infrastructure\Http\Handler\UpdatesHandler;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

return static function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    // Pages
    $app->get('/', HomeHandler::class, 'home');

    // SSE Updates (long-running multiplayer connection)
    $app->get('/updates', UpdatesHandler::class, 'updates');

    // Queries
    $app->get('/query/timeline', TimelineQueryHandler::class, 'query.timeline');
    $app->get('/query/items/{id:\d+}', ItemQueryHandler::class, 'query.items.get');

    // Commands - Items
    $app->post('/cmd/items', ItemCommandHandler::class . ':create', 'cmd.items.create');
    $app->put('/cmd/items/{id:\d+}', ItemCommandHandler::class . ':update', 'cmd.items.update');
    $app->delete('/cmd/items/{id:\d+}', ItemCommandHandler::class . ':delete', 'cmd.items.delete');

    // Commands - Groups
    $app->post('/cmd/groups', GroupCommandHandler::class . ':create', 'cmd.groups.create');
    $app->put('/cmd/groups/{id:\d+}', GroupCommandHandler::class . ':update', 'cmd.groups.update');
    $app->delete('/cmd/groups/{id:\d+}', GroupCommandHandler::class . ':delete', 'cmd.groups.delete');
    $app->put('/cmd/groups/reorder', GroupCommandHandler::class . ':reorder', 'cmd.groups.reorder');

    // Note: Static files (js/css) are handled by mezzio-swoole's built-in StaticResourceHandler
};
