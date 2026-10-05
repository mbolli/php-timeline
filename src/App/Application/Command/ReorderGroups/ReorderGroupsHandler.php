<?php

declare(strict_types=1);

namespace App\Application\Command\ReorderGroups;

use App\Domain\Event\TimelineChangedEvent;
use App\Domain\Repository\TimelineRepositoryInterface;
use App\Infrastructure\EventBus\EventBusInterface;

final class ReorderGroupsHandler {
    public function __construct(
        private readonly TimelineRepositoryInterface $repository,
        private readonly EventBusInterface $eventBus,
    ) {}

    public function __invoke(ReorderGroupsCommand $command): void {
        $this->repository->reorderGroups($command->orderedIds);

        $this->eventBus->emit(new TimelineChangedEvent(
            changeType: 'groups_reordered',
        ));
    }
}
