<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Listener;

use App\Application\Query\GetTimeline\GetTimelineHandler;
use App\Domain\Event\TimelineChangedEvent;
use App\Infrastructure\EventBus\EventBusInterface;
use App\Infrastructure\Template\TemplateRenderer;
use Mezzio\Swoole\Event\RequestEvent;
use starfederation\datastar\events\PatchElements;
use starfederation\datastar\ServerSentEventGenerator;
use Swoole\Timer;
use Throwable;

/**
 * SSE Request Listener for mezzio-swoole.
 *
 * This listener intercepts /updates requests and handles SSE streaming
 * directly using Swoole's response object, bypassing the standard PSR-7 flow.
 */
final class SseRequestListener
{
    public function __construct(
        private readonly EventBusInterface $eventBus,
        private readonly GetTimelineHandler $getTimelineHandler,
        private readonly TemplateRenderer $renderer,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $uri = $request->server['request_uri'] ?? '/';

        // Only handle /updates endpoint
        if (!str_starts_with($uri, '/updates')) {
            return;
        }

        $response = $event->getResponse();

        // Send SSE headers using Datastar SDK
        foreach (ServerSentEventGenerator::headers() as $name => $value) {
            $response->header($name, $value);
        }

        // Send initial timeline state
        $this->sendTimelineUpdate($response);

        // Subscribe to timeline changes
        $subscriptionId = $this->eventBus->subscribe(
            function (TimelineChangedEvent $e) use ($response): void {
                $this->sendTimelineUpdate($response);
            }
        );

        // Set up keep-alive timer (every 30 seconds)
        $timerId = Timer::tick(30000, function () use ($response, &$timerId, $subscriptionId): void {
            // Check if connection is still alive
            if (!$response->isWritable()) {
                Timer::clear($timerId);
                $this->eventBus->unsubscribe($subscriptionId);

                return;
            }

            // Send comment as keep-alive
            try {
                $response->write(": keep-alive\n\n");
            } catch (Throwable) {
                Timer::clear($timerId);
                $this->eventBus->unsubscribe($subscriptionId);
            }
        });

        // Mark that we've handled this request (stops propagation to other listeners)
        $event->responseSent();

        // Note: We don't call $response->end() - we keep the connection open for SSE
    }

    private function sendTimelineUpdate(\Swoole\Http\Response $response): void
    {
        try {
            $timeline = ($this->getTimelineHandler)();
            $html = $this->renderer->render('partials/timeline', [
                'groups' => $timeline['groups'],
                'bounds' => $timeline['bounds'],
            ]);

            // Use Datastar SDK's PatchElements event
            $event = new PatchElements($html);
            $output = $event->getOutput();

            $response->write($output);
        } catch (Throwable) {
            // Connection may have closed
        }
    }
}
