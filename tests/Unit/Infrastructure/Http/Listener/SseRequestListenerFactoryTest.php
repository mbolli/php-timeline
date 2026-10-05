<?php

declare(strict_types=1);

use App\Application\Query\GetTimeline\GetTimelineHandler;
use App\Infrastructure\EventBus\EventBusInterface;
use App\Infrastructure\EventBus\SwooleEventBus;
use App\Infrastructure\Http\Listener\SseRequestListener;
use App\Infrastructure\Http\Listener\SseRequestListenerFactory;
use App\Infrastructure\Persistence\SqliteTimelineRepository;
use App\Infrastructure\Template\TemplateRenderer;
use Psr\Container\ContainerInterface;

describe('SseRequestListenerFactory', function (): void {
    beforeEach(function (): void {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schema = file_get_contents(__DIR__ . '/../../../../../data/schema.sql');
        $this->pdo->exec($schema);

        $repository = new SqliteTimelineRepository($this->pdo);
        $eventBus = new SwooleEventBus();
        $getTimelineHandler = new GetTimelineHandler($repository);
        $renderer = new TemplateRenderer(realpath(__DIR__ . '/../../../../../templates'));

        $this->container = Mockery::mock(ContainerInterface::class);
        $this->container->shouldReceive('get')
            ->with(EventBusInterface::class)
            ->andReturn($eventBus)
        ;
        $this->container->shouldReceive('get')
            ->with(GetTimelineHandler::class)
            ->andReturn($getTimelineHandler)
        ;
        $this->container->shouldReceive('get')
            ->with(TemplateRenderer::class)
            ->andReturn($renderer)
        ;
    });

    afterEach(function (): void {
        Mockery::close();
    });

    it('creates SseRequestListener instance', function (): void {
        $factory = new SseRequestListenerFactory();
        $listener = $factory($this->container);

        expect($listener)->toBeInstanceOf(SseRequestListener::class);
    });

    it('creates callable listener', function (): void {
        $factory = new SseRequestListenerFactory();
        $listener = $factory($this->container);

        expect($listener)->toBeCallable();
    });
});
