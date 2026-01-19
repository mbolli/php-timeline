<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Handler\Command;

use App\Application\Command\CreateGroup\CreateGroupCommand;
use App\Application\Command\CreateGroup\CreateGroupHandler;
use App\Application\Command\DeleteGroup\DeleteGroupCommand;
use App\Application\Command\DeleteGroup\DeleteGroupHandler;
use App\Application\Command\UpdateGroup\UpdateGroupCommand;
use App\Application\Command\UpdateGroup\UpdateGroupHandler;
use App\Domain\Repository\TimelineRepositoryInterface;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class GroupCommandHandler {
    public function __construct(
        private readonly CreateGroupHandler $createHandler,
        private readonly UpdateGroupHandler $updateHandler,
        private readonly DeleteGroupHandler $deleteHandler,
        private readonly TimelineRepositoryInterface $repository,
    ) {}

    public function create(ServerRequestInterface $request): ResponseInterface {
        $data = $this->getRequestData($request);

        if (!isset($data['name'])) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        $command = CreateGroupCommand::fromArray($data);
        ($this->createHandler)($command);

        return new EmptyResponse(204);
    }

    public function update(ServerRequestInterface $request): ResponseInterface {
        $id = (int) $request->getAttribute('id');
        $data = $this->getRequestData($request);

        if (!isset($data['name'])) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        $command = UpdateGroupCommand::fromArray($id, $data);
        $group = ($this->updateHandler)($command);

        if ($group === null) {
            return new JsonResponse(['error' => 'Group not found'], 404);
        }

        return new EmptyResponse(204);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface {
        $id = (int) $request->getAttribute('id');

        $command = new DeleteGroupCommand($id);
        $result = ($this->deleteHandler)($command);

        if (!$result) {
            return new JsonResponse(['error' => 'Group not found'], 404);
        }

        return new EmptyResponse(204);
    }

    public function reorder(ServerRequestInterface $request): ResponseInterface {
        $data = $this->getRequestData($request);

        if (!isset($data['orderedIds']) || !\is_array($data['orderedIds'])) {
            return new JsonResponse(['error' => 'Missing orderedIds array'], 400);
        }

        $this->repository->reorderGroups($data['orderedIds']);

        return new EmptyResponse(204);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestData(ServerRequestInterface $request): array {
        $parsed = $request->getParsedBody();

        if (isset($parsed['datastar'])) {
            $data = $parsed['datastar'];

            // Check for nested signal objects (_newGroup, _editGroup)
            if (isset($data['_newGroup']) && \is_array($data['_newGroup'])) {
                return $data['_newGroup'];
            }
            if (isset($data['_editGroup']) && \is_array($data['_editGroup'])) {
                return $data['_editGroup'];
            }

            return $data;
        }

        return $parsed ?? [];
    }
}
