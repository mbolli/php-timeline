<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Handler;

use App\Application\Query\GetTimeline\GetTimelineHandler;
use App\Infrastructure\Template\TemplateRenderer;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HomeHandler implements RequestHandlerInterface {
    public function __construct(
        private readonly GetTimelineHandler $getTimelineHandler,
        private readonly TemplateRenderer $renderer,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface {
        $timeline = ($this->getTimelineHandler)();

        $html = $this->renderer->render('home', [
            'groups' => $timeline['groups'],
            'bounds' => $timeline['bounds'],
        ]);

        return new HtmlResponse($html);
    }
}
