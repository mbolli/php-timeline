<?php

declare(strict_types=1);

namespace App\Application\Query\GetTimeline;

use App\Domain\Model\TimelineGroup;
use App\Domain\Repository\TimelineRepositoryInterface;

final class GetTimelineHandler {
    public function __construct(
        private readonly TimelineRepositoryInterface $repository,
    ) {}

    /**
     * @return array{groups: TimelineGroup[], bounds: array{min: string, max: string}}
     */
    public function __invoke(): array {
        return [
            'groups' => $this->repository->getAllGroupsWithItems(),
            'bounds' => $this->repository->getTimelineBounds(),
        ];
    }
}
