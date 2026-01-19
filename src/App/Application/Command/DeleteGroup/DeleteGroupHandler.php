<?php

declare(strict_types=1);

namespace App\Application\Command\DeleteGroup;

use App\Domain\Event\TimelineChangedEvent;
use App\Domain\Repository\TimelineRepositoryInterface;
use App\Infrastructure\EventBus\EventBusInterface;

final class DeleteGroupHandler {
    public function __construct(
        private readonly TimelineRepositoryInterface $repository,
        private readonly EventBusInterface $eventBus,
    ) {}

    public function __invoke(DeleteGroupCommand $command): bool {
        $group = $this->repository->getGroupById($command->id);
        if ($group === null) {
            return false;
        }

        $result = $this->repository->deleteGroup($command->id);

        if ($result) {
            $this->eventBus->emit(new TimelineChangedEvent(
                changeType: 'group_deleted',
                groupId: $command->id,
            ));
        }

        return $result;
    }
}
