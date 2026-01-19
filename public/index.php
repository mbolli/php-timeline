<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

chdir(dirname(__DIR__));

require 'vendor/autoload.php';

(static function (): void {
    /** @var ContainerInterface $container */
    $container = require 'config/container.php';

    /** @var Application $app */
    $app = $container->get(Application::class);
    $factory = $container->get(MiddlewareFactory::class);

    // Execute programmatic/declarative middleware pipeline and target
    (require 'config/pipeline.php')($app, $factory, $container);
    (require 'config/routes.php')($app, $factory, $container);

    $app->run();
})();
