<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Handler;

use App\Application\Query\GetTimeline\GetTimelineHandler;
use App\Domain\Event\TimelineChangedEvent;
use App\Infrastructure\EventBus\EventBusInterface;
use App\Infrastructure\Template\TemplateRenderer;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use starfederation\datastar\events\PatchElements;
use starfederation\datastar\ServerSentEventGenerator;

final class UpdatesHandler implements RequestHandlerInterface {
    /** @var array<int, string> */
    private array $subscriptions = [];

    public function __construct(
        private readonly EventBusInterface $eventBus,
        private readonly GetTimelineHandler $getTimelineHandler,
        private readonly TemplateRenderer $renderer,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface {
        // Create streaming response with Datastar headers
        $response = new Response();
        foreach (ServerSentEventGenerator::headers() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        // For Swoole, we need to work with the response differently
        // The actual SSE streaming is handled in the Swoole server
        return $response->withHeader('X-SSE-Handler', 'updates');
    }

    /**
     * Called by Swoole server to handle SSE streaming.
     */
    public function handleSse(\Swoole\Http\Response $swooleResponse): void {
        // Send SSE headers using Datastar SDK
        foreach (ServerSentEventGenerator::headers() as $name => $value) {
            $swooleResponse->header($name, $value);
        }

        // Send initial timeline state
        $this->sendTimelineUpdate($swooleResponse);

        // Subscribe to timeline changes
        $subscriptionId = $this->eventBus->subscribe(
            function (TimelineChangedEvent $event) use ($swooleResponse): void {
                $this->sendTimelineUpdate($swooleResponse);
            }
        );

        // Store subscription ID for cleanup using fd as key
        /** @var int $fd */
        $fd = $swooleResponse->fd;
        $this->subscriptions[$fd] = $subscriptionId;
    }

    public function cleanup(\Swoole\Http\Response $swooleResponse): void {
        /** @var int $fd */
        $fd = $swooleResponse->fd;
        if (isset($this->subscriptions[$fd])) {
            $this->eventBus->unsubscribe($this->subscriptions[$fd]);
            unset($this->subscriptions[$fd]);
        }
    }

    private function sendTimelineUpdate(\Swoole\Http\Response $response): void {
        $timeline = ($this->getTimelineHandler)();
        $html = $this->renderer->render('partials/updates', [
            'groups' => $timeline['groups'],
            'bounds' => $timeline['bounds'],
        ]);

        // Use Datastar SDK's PatchElements event to generate output
        $event = new PatchElements($html);
        $output = $event->getOutput();

        $response->write($output);
    }
}
