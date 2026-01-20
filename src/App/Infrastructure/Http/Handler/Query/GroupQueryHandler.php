<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Handler\Query;

use App\Application\Query\GetGroup\GetGroupHandler;
use App\Infrastructure\Template\TemplateRenderer;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use starfederation\datastar\enums\ElementPatchMode;
use starfederation\datastar\events\PatchElements;
use starfederation\datastar\ServerSentEventGenerator;

final class GroupQueryHandler implements RequestHandlerInterface {
    public function __construct(
        private readonly GetGroupHandler $getGroupHandler,
        private readonly TemplateRenderer $renderer,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface {
        $id = (int) $request->getAttribute('id');
        $group = ($this->getGroupHandler)($id);

        if ($group === null) {
            return new Response\EmptyResponse(404);
        }

        $html = $this->renderer->render('partials/edit-group-modal', [
            'group' => $group,
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
