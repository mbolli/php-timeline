<?php

declare(strict_types=1);

namespace App\Application\Query\GetItem;

use App\Domain\Model\TimelineItem;
use App\Domain\Repository\TimelineRepositoryInterface;

final class GetItemHandler {
    public function __construct(
        private readonly TimelineRepositoryInterface $repository,
    ) {}

    public function __invoke(int $id): ?TimelineItem {
        return $this->repository->getItemById($id);
    }
}
