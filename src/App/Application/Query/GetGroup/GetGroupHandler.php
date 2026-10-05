<?php

declare(strict_types=1);

namespace App\Application\Query\GetGroup;

use App\Domain\Model\TimelineGroup;
use App\Domain\Repository\TimelineRepositoryInterface;

final class GetGroupHandler {
    public function __construct(
        private readonly TimelineRepositoryInterface $repository,
    ) {}

    /**
     * The group with its items, so the edit dialog can say how many items a delete removes.
     */
    public function __invoke(int $id): ?TimelineGroup {
        return $this->repository->getGroupById($id)?->withItems($this->repository->getItemsByGroupId($id));
    }
}
