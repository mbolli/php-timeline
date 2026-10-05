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
use App\Domain\Repository\TimelineRepositoryInterface;
use App\Infrastructure\Http\TimelineInput;
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
        private readonly TimelineRepositoryInterface $repository,
    ) {}

    public function create(ServerRequestInterface $request): ResponseInterface {
        [$data, $errors] = TimelineInput::item($this->getRequestData($request), $this->groupExists(...));
        if ($errors !== []) {
            return self::invalid($errors);
        }

        $command = CreateItemCommand::fromArray($data);
        $item = ($this->createHandler)($command);

        // Return 204 No Content - the SSE will push the update
        return new EmptyResponse(204);
    }

    public function update(ServerRequestInterface $request): ResponseInterface {
        $id = (int) $request->getAttribute('id');
        [$data, $errors] = TimelineInput::item($this->getRequestData($request), $this->groupExists(...));
        if ($errors !== []) {
            return self::invalid($errors);
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
        [$data, $errors] = TimelineInput::resize($this->getRequestData($request));
        if ($errors !== []) {
            return self::invalid($errors);
        }

        $command = ResizeItemCommand::fromArray($id, $data);
        $item = ($this->resizeHandler)($command);

        if ($item === null) {
            return new JsonResponse(['error' => 'Item not found'], 404);
        }

        return new EmptyResponse(204);
    }

    private function groupExists(int $id): bool {
        return $this->repository->getGroupById($id) !== null;
    }

    /**
     * @param array<string, string> $errors
     */
    private static function invalid(array $errors): ResponseInterface {
        return new JsonResponse(['error' => 'Invalid input', 'fields' => $errors], 422);
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
