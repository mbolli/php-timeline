<?php

declare(strict_types=1);

namespace App\Application\Command\ResizeItem;

use App\Domain\Event\TimelineChangedEvent;
use App\Domain\Model\TimelineItem;
use App\Domain\Repository\TimelineRepositoryInterface;
use App\Infrastructure\EventBus\EventBusInterface;

final class ResizeItemHandler {
    public function __construct(
        private readonly TimelineRepositoryInterface $repository,
        private readonly EventBusInterface $eventBus,
    ) {}

    public function __invoke(ResizeItemCommand $command): ?TimelineItem {
        $existing = $this->repository->getItemById($command->id);
        if ($existing === null) {
            return null;
        }

        // Create updated item, keeping all other properties the same
        $item = new TimelineItem(
            id: $existing->id,
            groupId: $existing->groupId,
            title: $existing->title,
            startDate: $command->startDate,
            endDate: $command->endDate,
            description: $existing->description,
            color: $existing->color,
            metadata: $existing->metadata,
        );

        $savedItem = $this->repository->saveItem($item);

        $this->eventBus->emit(new TimelineChangedEvent(
            changeType: 'item_resized',
            itemId: $savedItem->id,
            groupId: $savedItem->groupId,
        ));

        return $savedItem;
    }
}
