<?php

declare(strict_types=1);

namespace App\Application\Command\DeleteItem;

use App\Domain\Event\TimelineChangedEvent;
use App\Domain\Repository\TimelineRepositoryInterface;
use App\Infrastructure\EventBus\EventBusInterface;

final class DeleteItemHandler {
    public function __construct(
        private readonly TimelineRepositoryInterface $repository,
        private readonly EventBusInterface $eventBus,
    ) {}

    public function __invoke(DeleteItemCommand $command): bool {
        $item = $this->repository->getItemById($command->id);
        if ($item === null) {
            return false;
        }

        $groupId = $item->groupId;
        $result = $this->repository->deleteItem($command->id);

        if ($result) {
            $this->eventBus->emit(new TimelineChangedEvent(
                changeType: 'item_deleted',
                itemId: $command->id,
                groupId: $groupId,
            ));
        }

        return $result;
    }
}
