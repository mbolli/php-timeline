<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Handler;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class StaticFileHandler implements RequestHandlerInterface {
    /** @var array<string, string> */
    private const array MIME_TYPES = [
        'js' => 'application/javascript',
        'css' => 'text/css',
        'map' => 'application/json',
    ];

    public function handle(ServerRequestInterface $request): ResponseInterface {
        $file = $request->getAttribute('file');
        $path = $request->getUri()->getPath();

        // Determine base path from request path
        $basePath = match (true) {
            str_starts_with($path, '/js/') => 'public/js/',
            str_starts_with($path, '/css/') => 'public/css/',
            default => 'public/',
        };

        $filePath = realpath(__DIR__ . '/../../../../' . $basePath . $file);

        // Security: ensure file is within public directory
        $publicPath = realpath(__DIR__ . '/../../../../public');
        if ($filePath === false || !str_starts_with($filePath, $publicPath)) {
            return new Response\EmptyResponse(404);
        }

        if (!file_exists($filePath)) {
            return new Response\EmptyResponse(404);
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeType = self::MIME_TYPES[$extension] ?? 'application/octet-stream';

        $response = new Response();
        $response = $response->withHeader('Content-Type', $mimeType);
        $response = $response->withHeader('Cache-Control', 'public, max-age=31536000');
        $response->getBody()->write(file_get_contents($filePath));

        return $response;
    }
}
