<?php

declare(strict_types=1);

namespace App\Application\Command\CreateGroup;

use App\Domain\Event\TimelineChangedEvent;
use App\Domain\Model\TimelineGroup;
use App\Domain\Repository\TimelineRepositoryInterface;
use App\Infrastructure\EventBus\EventBusInterface;

final class CreateGroupHandler {
    public function __construct(
        private readonly TimelineRepositoryInterface $repository,
        private readonly EventBusInterface $eventBus,
    ) {}

    public function __invoke(CreateGroupCommand $command): TimelineGroup {
        $group = new TimelineGroup(
            id: null,
            name: $command->name,
            icon: $command->icon,
            color: $command->color,
            sortOrder: $command->sortOrder,
        );

        $savedGroup = $this->repository->saveGroup($group);

        $this->eventBus->emit(new TimelineChangedEvent(
            changeType: 'group_created',
            groupId: $savedGroup->id,
        ));

        return $savedGroup;
    }
}
