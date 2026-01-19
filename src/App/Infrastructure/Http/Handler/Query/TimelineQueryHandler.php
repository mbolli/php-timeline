<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Handler\Query;

use App\Application\Query\GetTimeline\GetTimelineHandler;
use App\Infrastructure\Template\TemplateRenderer;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TimelineQueryHandler implements RequestHandlerInterface {
    public function __construct(
        private readonly GetTimelineHandler $getTimelineHandler,
        private readonly TemplateRenderer $renderer,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface {
        $timeline = ($this->getTimelineHandler)();

        $html = $this->renderer->render('partials/timeline', [
            'groups' => $timeline['groups'],
            'bounds' => $timeline['bounds'],
        ]);

        // Return as SSE event for Datastar
        $response = new Response();
        $response = $response->withHeader('Content-Type', 'text/event-stream');

        $lines = explode("\n", $html);
        $body = "event: datastar-patch-elements\n";
        foreach ($lines as $line) {
            $body .= 'data: elements ' . $line . "\n";
        }
        $body .= "\n";

        $response->getBody()->write($body);

        return $response;
    }
}
