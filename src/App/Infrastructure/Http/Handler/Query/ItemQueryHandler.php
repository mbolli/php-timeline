<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Handler\Query;

use App\Application\Query\GetItem\GetItemHandler;
use App\Application\Query\GetTimeline\GetTimelineHandler;
use App\Infrastructure\Template\TemplateRenderer;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use starfederation\datastar\enums\ElementPatchMode;
use starfederation\datastar\events\PatchElements;
use starfederation\datastar\ServerSentEventGenerator;

final class ItemQueryHandler implements RequestHandlerInterface {
    public function __construct(
        private readonly GetItemHandler $getItemHandler,
        private readonly GetTimelineHandler $getTimelineHandler,
        private readonly TemplateRenderer $renderer,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface {
        $id = (int) $request->getAttribute('id');
        $item = ($this->getItemHandler)($id);

        if ($item === null) {
            return new Response\EmptyResponse(404);
        }

        $timeline = ($this->getTimelineHandler)();

        $html = $this->renderer->render('partials/edit-item-modal', [
            'item' => $item,
            'groups' => $timeline['groups'],
        ]);

        // Return as SSE PatchElements using Datastar SDK
        $response = new Response();
        foreach (ServerSentEventGenerator::headers() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $event = new PatchElements($html, [
            'selector' => '#app',
            'mode' => ElementPatchMode::Append,
        ]);
        $response->getBody()->write($event->getOutput());

        return $response;
    }
}
