<?php

declare(strict_types=1);

namespace App\Application\Command\UpdateGroup;

use App\Domain\Event\TimelineChangedEvent;
use App\Domain\Model\TimelineGroup;
use App\Domain\Repository\TimelineRepositoryInterface;
use App\Infrastructure\EventBus\EventBusInterface;

final class UpdateGroupHandler {
    public function __construct(
        private readonly TimelineRepositoryInterface $repository,
        private readonly EventBusInterface $eventBus,
    ) {}

    public function __invoke(UpdateGroupCommand $command): ?TimelineGroup {
        $existing = $this->repository->getGroupById($command->id);
        if ($existing === null) {
            return null;
        }

        $group = new TimelineGroup(
            id: $command->id,
            name: $command->name,
            icon: $command->icon,
            color: $command->color,
            sortOrder: $command->sortOrder,
        );

        $savedGroup = $this->repository->saveGroup($group);

        $this->eventBus->emit(new TimelineChangedEvent(
            changeType: 'group_updated',
            groupId: $savedGroup->id,
        ));

        return $savedGroup;
    }
}
