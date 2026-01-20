<?php

declare(strict_types=1);

namespace App;

use App\Application\Command\CreateGroup\CreateGroupHandler;
use App\Application\Command\CreateItem\CreateItemHandler;
use App\Application\Command\DeleteGroup\DeleteGroupHandler;
use App\Application\Command\DeleteItem\DeleteItemHandler;
use App\Application\Command\ReorderGroups\ReorderGroupsHandler;
use App\Application\Command\ResizeItem\ResizeItemHandler;
use App\Application\Command\UpdateGroup\UpdateGroupHandler;
use App\Application\Command\UpdateItem\UpdateItemHandler;
use App\Application\Query\GetGroup\GetGroupHandler;
use App\Application\Query\GetItem\GetItemHandler;
use App\Application\Query\GetTimeline\GetTimelineHandler;
use App\Domain\Repository\TimelineRepositoryInterface;
use App\Infrastructure\EventBus\EventBusInterface;
use App\Infrastructure\EventBus\SwooleEventBus;
use App\Infrastructure\Http\Handler\Command\GroupCommandHandler;
use App\Infrastructure\Http\Handler\Command\ItemCommandHandler;
use App\Infrastructure\Http\Handler\HomeHandler;
use App\Infrastructure\Http\Handler\Query\GroupQueryHandler;
use App\Infrastructure\Http\Handler\Query\ItemQueryHandler;
use App\Infrastructure\Http\Handler\Query\TimelineQueryHandler;
use App\Infrastructure\Http\Handler\StaticFileHandler;
use App\Infrastructure\Http\Handler\UpdatesHandler;
use App\Infrastructure\Http\Listener\SseRequestListener;
use App\Infrastructure\Http\Listener\SseRequestListenerFactory;
use App\Infrastructure\Http\Middleware\JsonBodyParserMiddleware;
use App\Infrastructure\Persistence\SqliteTimelineRepository;
use App\Infrastructure\Template\TemplateRenderer;
use PDO;
use Psr\Container\ContainerInterface;

final class ConfigProvider {
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getDependencies(): array {
        return [
            'factories' => [
                // PDO
                \PDO::class => function (ContainerInterface $container): \PDO {
                    $config = $container->get('config');
                    $dsn = $config['database']['dsn'];

                    $pdo = new \PDO($dsn);
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

                    // Initialize schema if database is empty
                    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll();
                    if (empty($tables)) {
                        $schemaPath = realpath(__DIR__ . '/../data/schema.sql');
                        if ($schemaPath && file_exists($schemaPath)) {
                            $pdo->exec(file_get_contents($schemaPath));
                        }
                    }

                    return $pdo;
                },

                // Template Renderer
                TemplateRenderer::class => function (ContainerInterface $container): TemplateRenderer {
                    $config = $container->get('config');
                    $paths = $config['templates']['paths']['app'] ?? [];
                    $templatesPath = $paths[0] ?? realpath(__DIR__ . '/../templates');

                    return new TemplateRenderer($templatesPath);
                },

                // Event Bus (singleton for Swoole)
                EventBusInterface::class => function (): EventBusInterface {
                    static $eventBus = null;
                    if ($eventBus === null) {
                        $eventBus = new SwooleEventBus();
                    }

                    return $eventBus;
                },

                // Repository
                TimelineRepositoryInterface::class => fn (ContainerInterface $container): TimelineRepositoryInterface => new SqliteTimelineRepository($container->get(\PDO::class)),

                // Query Handlers
                GetTimelineHandler::class => fn (ContainerInterface $container): GetTimelineHandler => new GetTimelineHandler(
                    $container->get(TimelineRepositoryInterface::class)
                ),

                GetItemHandler::class => fn (ContainerInterface $container): GetItemHandler => new GetItemHandler(
                    $container->get(TimelineRepositoryInterface::class)
                ),

                GetGroupHandler::class => fn (ContainerInterface $container): GetGroupHandler => new GetGroupHandler(
                    $container->get(TimelineRepositoryInterface::class)
                ),

                // Command Handlers
                CreateItemHandler::class => fn (ContainerInterface $container): CreateItemHandler => new CreateItemHandler(
                    $container->get(TimelineRepositoryInterface::class),
                    $container->get(EventBusInterface::class)
                ),

                UpdateItemHandler::class => fn (ContainerInterface $container): UpdateItemHandler => new UpdateItemHandler(
                    $container->get(TimelineRepositoryInterface::class),
                    $container->get(EventBusInterface::class)
                ),

                DeleteItemHandler::class => fn (ContainerInterface $container): DeleteItemHandler => new DeleteItemHandler(
                    $container->get(TimelineRepositoryInterface::class),
                    $container->get(EventBusInterface::class)
                ),

                ResizeItemHandler::class => fn (ContainerInterface $container): ResizeItemHandler => new ResizeItemHandler(
                    $container->get(TimelineRepositoryInterface::class),
                    $container->get(EventBusInterface::class)
                ),

                CreateGroupHandler::class => fn (ContainerInterface $container): CreateGroupHandler => new CreateGroupHandler(
                    $container->get(TimelineRepositoryInterface::class),
                    $container->get(EventBusInterface::class)
                ),

                UpdateGroupHandler::class => fn (ContainerInterface $container): UpdateGroupHandler => new UpdateGroupHandler(
                    $container->get(TimelineRepositoryInterface::class),
                    $container->get(EventBusInterface::class)
                ),

                DeleteGroupHandler::class => fn (ContainerInterface $container): DeleteGroupHandler => new DeleteGroupHandler(
                    $container->get(TimelineRepositoryInterface::class),
                    $container->get(EventBusInterface::class)
                ),

                ReorderGroupsHandler::class => fn (ContainerInterface $container): ReorderGroupsHandler => new ReorderGroupsHandler(
                    $container->get(TimelineRepositoryInterface::class),
                    $container->get(EventBusInterface::class)
                ),

                // HTTP Handlers
                HomeHandler::class => fn (ContainerInterface $container): HomeHandler => new HomeHandler(
                    $container->get(GetTimelineHandler::class),
                    $container->get(TemplateRenderer::class)
                ),

                UpdatesHandler::class => fn (ContainerInterface $container): UpdatesHandler => new UpdatesHandler(
                    $container->get(EventBusInterface::class),
                    $container->get(GetTimelineHandler::class),
                    $container->get(TemplateRenderer::class)
                ),

                TimelineQueryHandler::class => fn (ContainerInterface $container): TimelineQueryHandler => new TimelineQueryHandler(
                    $container->get(GetTimelineHandler::class),
                    $container->get(TemplateRenderer::class)
                ),

                ItemQueryHandler::class => fn (ContainerInterface $container): ItemQueryHandler => new ItemQueryHandler(
                    $container->get(GetItemHandler::class),
                    $container->get(GetTimelineHandler::class),
                    $container->get(TemplateRenderer::class)
                ),

                GroupQueryHandler::class => fn (ContainerInterface $container): GroupQueryHandler => new GroupQueryHandler(
                    $container->get(GetGroupHandler::class),
                    $container->get(TemplateRenderer::class)
                ),

                ItemCommandHandler::class => fn (ContainerInterface $container): ItemCommandHandler => new ItemCommandHandler(
                    $container->get(CreateItemHandler::class),
                    $container->get(UpdateItemHandler::class),
                    $container->get(DeleteItemHandler::class),
                    $container->get(ResizeItemHandler::class)
                ),

                // Route method handlers for Items
                ItemCommandHandler::class . ':create' => fn (ContainerInterface $container) => new class($container->get(ItemCommandHandler::class)) implements \Psr\Http\Server\RequestHandlerInterface {
                    public function __construct(private readonly ItemCommandHandler $handler) {}
                    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                        return $this->handler->create($request);
                    }
                },
                ItemCommandHandler::class . ':update' => fn (ContainerInterface $container) => new class($container->get(ItemCommandHandler::class)) implements \Psr\Http\Server\RequestHandlerInterface {
                    public function __construct(private readonly ItemCommandHandler $handler) {}
                    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                        return $this->handler->update($request);
                    }
                },
                ItemCommandHandler::class . ':delete' => fn (ContainerInterface $container) => new class($container->get(ItemCommandHandler::class)) implements \Psr\Http\Server\RequestHandlerInterface {
                    public function __construct(private readonly ItemCommandHandler $handler) {}
                    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                        return $this->handler->delete($request);
                    }
                },
                ItemCommandHandler::class . ':resize' => fn (ContainerInterface $container) => new class($container->get(ItemCommandHandler::class)) implements \Psr\Http\Server\RequestHandlerInterface {
                    public function __construct(private readonly ItemCommandHandler $handler) {}
                    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                        return $this->handler->resize($request);
                    }
                },

                GroupCommandHandler::class => fn (ContainerInterface $container): GroupCommandHandler => new GroupCommandHandler(
                    $container->get(CreateGroupHandler::class),
                    $container->get(UpdateGroupHandler::class),
                    $container->get(DeleteGroupHandler::class),
                    $container->get(ReorderGroupsHandler::class)
                ),

                // Route method handlers for Groups
                GroupCommandHandler::class . ':create' => fn (ContainerInterface $container) => new class($container->get(GroupCommandHandler::class)) implements \Psr\Http\Server\RequestHandlerInterface {
                    public function __construct(private readonly GroupCommandHandler $handler) {}
                    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                        return $this->handler->create($request);
                    }
                },
                GroupCommandHandler::class . ':update' => fn (ContainerInterface $container) => new class($container->get(GroupCommandHandler::class)) implements \Psr\Http\Server\RequestHandlerInterface {
                    public function __construct(private readonly GroupCommandHandler $handler) {}
                    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                        return $this->handler->update($request);
                    }
                },
                GroupCommandHandler::class . ':delete' => fn (ContainerInterface $container) => new class($container->get(GroupCommandHandler::class)) implements \Psr\Http\Server\RequestHandlerInterface {
                    public function __construct(private readonly GroupCommandHandler $handler) {}
                    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                        return $this->handler->delete($request);
                    }
                },
                GroupCommandHandler::class . ':reorder' => fn (ContainerInterface $container) => new class($container->get(GroupCommandHandler::class)) implements \Psr\Http\Server\RequestHandlerInterface {
                    public function __construct(private readonly GroupCommandHandler $handler) {}
                    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                        return $this->handler->reorder($request);
                    }
                },

                StaticFileHandler::class => fn (): StaticFileHandler => new StaticFileHandler(),

                // SSE Listener for mezzio-swoole
                SseRequestListener::class => SseRequestListenerFactory::class,

                // Middleware
                JsonBodyParserMiddleware::class => fn (): JsonBodyParserMiddleware => new JsonBodyParserMiddleware(),
            ],
        ];
    }
}
