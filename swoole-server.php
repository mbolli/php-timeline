<?php

declare(strict_types=1);

use App\Application\Query\GetTimeline\GetTimelineHandler;
use App\Infrastructure\EventBus\EventBusInterface;
use App\Infrastructure\Http\Handler\UpdatesHandler;
use App\Infrastructure\Template\TemplateRenderer;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use starfederation\datastar\events\PatchElements;
use starfederation\datastar\ServerSentEventGenerator;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Timer;

chdir(__DIR__);

require 'vendor/autoload.php';

// Configuration
$host = getenv('SWOOLE_HOST') ?: '0.0.0.0';
$port = (int) (getenv('SWOOLE_PORT') ?: 8080);

/** @var ContainerInterface $container */
$container = require 'config/container.php';

/** @var Application $app */
$app = $container->get(Application::class);
$factory = $container->get(MiddlewareFactory::class);

// Execute programmatic/declarative middleware pipeline and routes
(require 'config/pipeline.php')($app, $factory, $container);
(require 'config/routes.php')($app, $factory, $container);

// Create Swoole HTTP Server
$server = new Server($host, $port);

$server->set([
    'worker_num' => swoole_cpu_num(),
    'enable_coroutine' => true,
    'open_http2_protocol' => false,
]);

// Store active SSE connections
$sseConnections = new SplObjectStorage();

$server->on('start', function (Server $server) use ($host, $port): void {
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║           Life Timeline - Swoole Server Started              ║\n";
    echo "╠══════════════════════════════════════════════════════════════╣\n";
    echo "║  URL: http://{$host}:{$port}                                    ║\n";
    echo '║  Workers: ' . swoole_cpu_num() . "                                                     ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
});

$server->on('request', function (Request $swooleRequest, Response $swooleResponse) use ($app, $container, &$sseConnections): void {
    $uri = $swooleRequest->server['request_uri'] ?? '/';
    $method = $swooleRequest->server['request_method'] ?? 'GET';

    // Handle static files directly (js, css, map files)
    if (preg_match('#^/(js|css)/(.+)$#', $uri, $matches)) {
        $type = $matches[1];
        $file = $matches[2];

        $mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'map' => 'application/json',
        ];

        $filePath = __DIR__ . '/public/' . $type . '/' . $file;

        if (file_exists($filePath) && is_file($filePath)) {
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

            $swooleResponse->header('Content-Type', $mimeType);
            $swooleResponse->header('Cache-Control', 'public, max-age=31536000');
            $swooleResponse->sendfile($filePath);
        } else {
            $swooleResponse->status(404);
            $swooleResponse->end('Not Found');
        }

        return;
    }

    // Handle SSE updates endpoint specially
    if ($uri === '/updates' && $method === 'GET') {
        handleSseConnection($swooleResponse, $container, $sseConnections);

        return;
    }

    // Convert Swoole request to PSR-7
    $psrRequest = convertSwooleRequest($swooleRequest);

    try {
        // Run through Mezzio
        $psrResponse = $app->handle($psrRequest);

        // Convert PSR-7 response to Swoole
        sendSwooleResponse($swooleResponse, $psrResponse);
    } catch (Throwable $e) {
        $swooleResponse->status(500);
        $swooleResponse->header('Content-Type', 'text/plain');
        $swooleResponse->end('Internal Server Error: ' . $e->getMessage());
    }
});

$server->on('close', function (Server $server, int $fd) use (&$sseConnections, $container): void {
    // Clean up SSE connection when client disconnects
    foreach ($sseConnections as $response) {
        if ($sseConnections[$response] === $fd) {
            /** @var UpdatesHandler $updatesHandler */
            $updatesHandler = $container->get(UpdatesHandler::class);
            $updatesHandler->cleanup($response);
            $sseConnections->detach($response);

            break;
        }
    }
});

/**
 * Handle SSE connection for real-time updates.
 */
function handleSseConnection(Response $swooleResponse, ContainerInterface $container, SplObjectStorage $sseConnections): void {
    // Set SSE headers using Datastar SDK
    foreach (ServerSentEventGenerator::headers() as $name => $value) {
        $swooleResponse->header($name, $value);
    }
    $swooleResponse->header('Access-Control-Allow-Origin', '*');

    // Get event bus and handlers
    /** @var EventBusInterface $eventBus */
    $eventBus = $container->get(EventBusInterface::class);

    /** @var GetTimelineHandler $getTimelineHandler */
    $getTimelineHandler = $container->get(GetTimelineHandler::class);

    /** @var TemplateRenderer $renderer */
    $renderer = $container->get(TemplateRenderer::class);

    // Send initial timeline state
    $timeline = $getTimelineHandler();
    $html = $renderer->render('partials/timeline', [
        'groups' => $timeline['groups'],
        'bounds' => $timeline['bounds'],
    ]);

    sendDatastarPatch($swooleResponse, $html);

    // Subscribe to timeline changes
    $subscriptionId = $eventBus->subscribe(function ($event) use ($swooleResponse, $getTimelineHandler, $renderer): void {
        $timeline = $getTimelineHandler();
        $html = $renderer->render('partials/timeline', [
            'groups' => $timeline['groups'],
            'bounds' => $timeline['bounds'],
        ]);

        sendDatastarPatch($swooleResponse, $html);
    });

    // Store for cleanup
    $swooleResponse->subscriptionId = $subscriptionId;
    $sseConnections->attach($swooleResponse, $swooleResponse->fd);

    // Send keep-alive ping every 30 seconds
    Timer::tick(30000, function ($timerId) use ($swooleResponse, $eventBus, $subscriptionId, $sseConnections): void {
        // Check if connection is still alive
        if (!$sseConnections->contains($swooleResponse)) {
            Timer::clear($timerId);
            $eventBus->unsubscribe($subscriptionId);

            return;
        }

        // Send comment as keep-alive
        try {
            $swooleResponse->write(": keep-alive\n\n");
        } catch (Throwable $e) {
            Timer::clear($timerId);
            $eventBus->unsubscribe($subscriptionId);
            $sseConnections->detach($swooleResponse);
        }
    });
}

/**
 * Send Datastar patch elements event using SDK.
 */
function sendDatastarPatch(Response $response, string $html): void {
    $event = new PatchElements($html);
    $output = $event->getOutput();

    try {
        $response->write($output);
    } catch (Throwable $e) {
        // Connection likely closed
    }
}

/**
 * Convert Swoole request to PSR-7.
 */
function convertSwooleRequest(Request $swooleRequest): ServerRequestInterface {
    $uri = $swooleRequest->server['request_uri'] ?? '/';
    $method = $swooleRequest->server['request_method'] ?? 'GET';
    $queryString = $swooleRequest->server['query_string'] ?? '';

    if ($queryString) {
        $uri .= '?' . $queryString;
    }

    $headers = [];
    foreach ($swooleRequest->header ?? [] as $name => $value) {
        $headers[ucwords($name, '-')] = $value;
    }

    $body = $swooleRequest->rawContent() ?: '';

    $request = new ServerRequest(
        serverParams: $swooleRequest->server ?? [],
        uploadedFiles: [],
        uri: $uri,
        method: $method,
        body: new Stream('php://temp', 'r+'),
        headers: $headers,
    );

    $request->getBody()->write($body);
    $request->getBody()->rewind();

    // Parse body for JSON requests
    $contentType = $headers['Content-Type'] ?? '';
    if (str_contains($contentType, 'application/json') && $body) {
        $parsed = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $request = $request->withParsedBody($parsed);
        }
    }

    return $request;
}

/**
 * Convert PSR-7 response to Swoole response.
 */
function sendSwooleResponse(Response $swooleResponse, ResponseInterface $psrResponse): void {
    // Set status code
    $swooleResponse->status($psrResponse->getStatusCode());

    // Set headers
    foreach ($psrResponse->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            $swooleResponse->header($name, $value);
        }
    }

    // Check if this is an SSE response that shouldn't end immediately
    $contentType = $psrResponse->getHeaderLine('Content-Type');
    if (str_contains($contentType, 'text/event-stream')) {
        // For SSE, write body and keep connection open
        $body = (string) $psrResponse->getBody();
        if ($body) {
            $swooleResponse->write($body);
        }

        return;
    }

    // Send body and end connection
    $swooleResponse->end((string) $psrResponse->getBody());
}

// Start the server
$server->start();
