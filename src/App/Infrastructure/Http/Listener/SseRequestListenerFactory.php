<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Listener;

use App\Application\Query\GetTimeline\GetTimelineHandler;
use App\Infrastructure\EventBus\EventBusInterface;
use App\Infrastructure\Template\TemplateRenderer;
use Psr\Container\ContainerInterface;

final class SseRequestListenerFactory
{
    public function __invoke(ContainerInterface $container): SseRequestListener
    {
        return new SseRequestListener(
            $container->get(EventBusInterface::class),
            $container->get(GetTimelineHandler::class),
            $container->get(TemplateRenderer::class),
        );
    }
}
