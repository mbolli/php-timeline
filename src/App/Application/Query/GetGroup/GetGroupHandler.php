<?php

declare(strict_types=1);

namespace App\Application\Query\GetGroup;

use App\Domain\Model\TimelineGroup;
use App\Domain\Repository\TimelineRepositoryInterface;

final class GetGroupHandler {
    public function __construct(
        private readonly TimelineRepositoryInterface $repository,
    ) {}

    public function __invoke(int $id): ?TimelineGroup {
        return $this->repository->getGroupById($id);
    }
}
