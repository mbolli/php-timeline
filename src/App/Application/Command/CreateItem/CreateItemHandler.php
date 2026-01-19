<?php

declare(strict_types=1);

namespace App\Application\Command\CreateItem;

use App\Domain\Event\TimelineChangedEvent;
use App\Domain\Model\TimelineItem;
use App\Domain\Repository\TimelineRepositoryInterface;
use App\Infrastructure\EventBus\EventBusInterface;

final class CreateItemHandler {
    public function __construct(
        private readonly TimelineRepositoryInterface $repository,
        private readonly EventBusInterface $eventBus,
    ) {}

    public function __invoke(CreateItemCommand $command): TimelineItem {
        $item = new TimelineItem(
            id: null,
            groupId: $command->groupId,
            title: $command->title,
            startDate: $command->startDate,
            endDate: $command->endDate,
            description: $command->description,
            color: $command->color,
            metadata: $command->metadata,
        );

        $savedItem = $this->repository->saveItem($item);

        $this->eventBus->emit(new TimelineChangedEvent(
            changeType: 'item_created',
            itemId: $savedItem->id,
            groupId: $savedItem->groupId,
        ));

        return $savedItem;
    }
}
