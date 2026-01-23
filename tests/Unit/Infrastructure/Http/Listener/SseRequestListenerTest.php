<?php

declare(strict_types=1);

use App\Application\Query\GetTimeline\GetTimelineHandler;
use App\Infrastructure\EventBus\EventBusInterface;
use App\Infrastructure\EventBus\SwooleEventBus;
use App\Infrastructure\Http\Listener\SseRequestListener;
use App\Infrastructure\Persistence\SqliteTimelineRepository;
use App\Infrastructure\Template\TemplateRenderer;
use Mezzio\Swoole\Event\RequestEvent;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;

// Skip entire test suite - requires Swoole coroutine runtime
// These tests use Swoole\Coroutine\Channel and Swoole\Timer which hang outside coroutine context
// Run with: php -d swoole.use_shortname='Off' vendor/bin/pest --group=swoole
describe('SseRequestListener', function (): void {
    beforeEach(function (): void {
        // Skip if not in Swoole coroutine context
        if (!class_exists(\Swoole\Coroutine::class) || \Swoole\Coroutine::getCid() < 0) {
            $this->markTestSkipped('Requires Swoole coroutine runtime');
        }

        // Set up real dependencies for integration testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schema = file_get_contents(__DIR__ . '/../../../../../data/schema.sql');
        $this->pdo->exec($schema);

        $repository = new SqliteTimelineRepository($this->pdo);
        $this->eventBus = new SwooleEventBus();
        $this->getTimelineHandler = new GetTimelineHandler($repository);
        $this->renderer = new TemplateRenderer(realpath(__DIR__ . '/../../../../../templates'));

        $this->listener = new SseRequestListener(
            $this->eventBus,
            $this->getTimelineHandler,
            $this->renderer,
        );
    });

    afterEach(function (): void {
        Mockery::close();
    });

    describe('route matching', function (): void {
        it('handles /updates endpoint', function (): void {
            $swooleRequest = Mockery::mock(SwooleRequest::class);
            $swooleRequest->server = ['request_uri' => '/updates'];

            $writeCount = 0;
            $swooleResponse = Mockery::mock(SwooleResponse::class);
            $swooleResponse->shouldReceive('header')->andReturn(true);
            $swooleResponse->shouldReceive('write')->andReturn(true);
            // Return false after first check to break the SSE loop
            $swooleResponse->shouldReceive('isWritable')->andReturnUsing(function () use (&$writeCount) {
                return ++$writeCount <= 1;
            });

            $event = Mockery::mock(RequestEvent::class);
            $event->shouldReceive('getRequest')->andReturn($swooleRequest);
            $event->shouldReceive('getResponse')->andReturn($swooleResponse);
            $event->shouldReceive('responseSent')->once();

            ($this->listener)($event);

            // If we got here without exception, the handler processed the request
            expect(true)->toBeTrue();
        });

        it('handles /updates with query parameters', function (): void {
            $swooleRequest = Mockery::mock(SwooleRequest::class);
            $swooleRequest->server = ['request_uri' => '/updates?datastar={}'];

            $writeCount = 0;
            $swooleResponse = Mockery::mock(SwooleResponse::class);
            $swooleResponse->shouldReceive('header')->andReturn(true);
            $swooleResponse->shouldReceive('write')->andReturn(true);
            $swooleResponse->shouldReceive('isWritable')->andReturnUsing(function () use (&$writeCount) {
                return ++$writeCount <= 1;
            });

            $event = Mockery::mock(RequestEvent::class);
            $event->shouldReceive('getRequest')->andReturn($swooleRequest);
            $event->shouldReceive('getResponse')->andReturn($swooleResponse);
            $event->shouldReceive('responseSent')->once();

            ($this->listener)($event);

            expect(true)->toBeTrue();
        });

        it('ignores non-updates endpoints', function (): void {
            $swooleRequest = Mockery::mock(SwooleRequest::class);
            $swooleRequest->server = ['request_uri' => '/'];

            $event = Mockery::mock(RequestEvent::class);
            $event->shouldReceive('getRequest')->andReturn($swooleRequest);
            $event->shouldNotReceive('getResponse');
            $event->shouldNotReceive('responseSent');

            ($this->listener)($event);

            // Should not have called any response methods
            expect(true)->toBeTrue();
        });

        it('ignores /api/items endpoint', function (): void {
            $swooleRequest = Mockery::mock(SwooleRequest::class);
            $swooleRequest->server = ['request_uri' => '/api/items'];

            $event = Mockery::mock(RequestEvent::class);
            $event->shouldReceive('getRequest')->andReturn($swooleRequest);
            $event->shouldNotReceive('getResponse');
            $event->shouldNotReceive('responseSent');

            ($this->listener)($event);

            expect(true)->toBeTrue();
        });
    });

    describe('SSE headers', function (): void {
        it('sets correct SSE headers', function (): void {
            $swooleRequest = Mockery::mock(SwooleRequest::class);
            $swooleRequest->server = ['request_uri' => '/updates'];

            $headersSet = [];
            $writeCount = 0;
            $swooleResponse = Mockery::mock(SwooleResponse::class);
            $swooleResponse->shouldReceive('header')->andReturnUsing(
                function ($name, $value) use (&$headersSet) {
                    $headersSet[$name] = $value;

                    return true;
                }
            );
            $swooleResponse->shouldReceive('write')->andReturn(true);
            $swooleResponse->shouldReceive('isWritable')->andReturnUsing(function () use (&$writeCount) {
                return ++$writeCount <= 1;
            });

            $event = Mockery::mock(RequestEvent::class);
            $event->shouldReceive('getRequest')->andReturn($swooleRequest);
            $event->shouldReceive('getResponse')->andReturn($swooleResponse);
            $event->shouldReceive('responseSent');

            ($this->listener)($event);

            expect($headersSet)->toHaveKey('Content-Type');
            expect($headersSet['Content-Type'])->toBe('text/event-stream');
            expect($headersSet)->toHaveKey('Cache-Control');
            expect($headersSet['Cache-Control'])->toBe('no-cache');
        });
    });

    describe('initial timeline send', function (): void {
        it('sends initial timeline state on connection', function (): void {
            $swooleRequest = Mockery::mock(SwooleRequest::class);
            $swooleRequest->server = ['request_uri' => '/updates'];

            $writtenData = [];
            $writeCount = 0;
            $swooleResponse = Mockery::mock(SwooleResponse::class);
            $swooleResponse->shouldReceive('header')->andReturn(true);
            $swooleResponse->shouldReceive('write')->andReturnUsing(
                function ($data) use (&$writtenData) {
                    $writtenData[] = $data;

                    return true;
                }
            );
            $swooleResponse->shouldReceive('isWritable')->andReturnUsing(function () use (&$writeCount) {
                return ++$writeCount <= 1;
            });

            $event = Mockery::mock(RequestEvent::class);
            $event->shouldReceive('getRequest')->andReturn($swooleRequest);
            $event->shouldReceive('getResponse')->andReturn($swooleResponse);
            $event->shouldReceive('responseSent');

            ($this->listener)($event);

            expect($writtenData)->not->toBeEmpty();
            // Datastar PatchElements format
            expect($writtenData[0])->toContain('event: datastar-patch-elements');
        });
    });

    describe('propagation', function (): void {
        it('stops event propagation after handling', function (): void {
            $swooleRequest = Mockery::mock(SwooleRequest::class);
            $swooleRequest->server = ['request_uri' => '/updates'];

            $writeCount = 0;
            $swooleResponse = Mockery::mock(SwooleResponse::class);
            $swooleResponse->shouldReceive('header')->andReturn(true);
            $swooleResponse->shouldReceive('write')->andReturn(true);
            $swooleResponse->shouldReceive('isWritable')->andReturnUsing(function () use (&$writeCount) {
                return ++$writeCount <= 1;
            });

            $event = Mockery::mock(RequestEvent::class);
            $event->shouldReceive('getRequest')->andReturn($swooleRequest);
            $event->shouldReceive('getResponse')->andReturn($swooleResponse);
            $event->shouldReceive('responseSent')->once();

            ($this->listener)($event);

            // responseSent() was called exactly once - verified by Mockery
            expect(true)->toBeTrue();
        });
    });
});
