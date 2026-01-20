<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Handler\Command;

use App\Application\Command\CreateItem\CreateItemCommand;
use App\Application\Command\CreateItem\CreateItemHandler;
use App\Application\Command\DeleteItem\DeleteItemCommand;
use App\Application\Command\DeleteItem\DeleteItemHandler;
use App\Application\Command\ResizeItem\ResizeItemCommand;
use App\Application\Command\ResizeItem\ResizeItemHandler;
use App\Application\Command\UpdateItem\UpdateItemCommand;
use App\Application\Command\UpdateItem\UpdateItemHandler;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ItemCommandHandler {
    public function __construct(
        private readonly CreateItemHandler $createHandler,
        private readonly UpdateItemHandler $updateHandler,
        private readonly DeleteItemHandler $deleteHandler,
        private readonly ResizeItemHandler $resizeHandler,
    ) {}

    public function create(ServerRequestInterface $request): ResponseInterface {
        $data = $this->getRequestData($request);

        if (!isset($data['groupId'], $data['title'], $data['startDate'])) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        $command = CreateItemCommand::fromArray($data);
        $item = ($this->createHandler)($command);

        // Return 204 No Content - the SSE will push the update
        return new EmptyResponse(204);
    }

    public function update(ServerRequestInterface $request): ResponseInterface {
        $id = (int) $request->getAttribute('id');
        $data = $this->getRequestData($request);

        if (!isset($data['groupId'], $data['title'], $data['startDate'])) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        $command = UpdateItemCommand::fromArray($id, $data);
        $item = ($this->updateHandler)($command);

        if ($item === null) {
            return new JsonResponse(['error' => 'Item not found'], 404);
        }

        return new EmptyResponse(204);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface {
        $id = (int) $request->getAttribute('id');

        $command = new DeleteItemCommand($id);
        $result = ($this->deleteHandler)($command);

        if (!$result) {
            return new JsonResponse(['error' => 'Item not found'], 404);
        }

        return new EmptyResponse(204);
    }

    public function resize(ServerRequestInterface $request): ResponseInterface {
        $id = (int) $request->getAttribute('id');
        $data = $this->getRequestData($request);

        if (!isset($data['startDate'])) {
            return new JsonResponse(['error' => 'Missing required startDate field'], 400);
        }

        $command = ResizeItemCommand::fromArray($id, $data);
        $item = ($this->resizeHandler)($command);

        if ($item === null) {
            return new JsonResponse(['error' => 'Item not found'], 404);
        }

        return new EmptyResponse(204);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestData(ServerRequestInterface $request): array {
        $parsed = $request->getParsedBody();

        // Datastar JSON sends signals in a 'datastar' wrapper
        if (isset($parsed['datastar'])) {
            $data = $parsed['datastar'];

            // Check for nested signal objects (_newItem, _editItem)
            if (isset($data['_newItem']) && \is_array($data['_newItem'])) {
                return $data['_newItem'];
            }
            if (isset($data['_editItem']) && \is_array($data['_editItem'])) {
                return $data['_editItem'];
            }

            return $data;
        }

        // Form-encoded data comes as flat key-value pairs
        return $parsed ?? [];
    }
}
