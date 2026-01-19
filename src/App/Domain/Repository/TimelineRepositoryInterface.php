<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\TimelineGroup;
use App\Domain\Model\TimelineItem;

interface TimelineRepositoryInterface {
    // Groups
    /** @return TimelineGroup[] */
    public function getAllGroups(): array;

    /** @return TimelineGroup[] with items populated */
    public function getAllGroupsWithItems(): array;

    public function getGroupById(int $id): ?TimelineGroup;

    public function saveGroup(TimelineGroup $group): TimelineGroup;

    public function deleteGroup(int $id): bool;

    /**
     * @param list<int> $orderedIds
     */
    public function reorderGroups(array $orderedIds): bool;

    // Items
    /** @return TimelineItem[] */
    public function getAllItems(): array;

    /** @return TimelineItem[] */
    public function getItemsByGroupId(int $groupId): array;

    public function getItemById(int $id): ?TimelineItem;

    public function saveItem(TimelineItem $item): TimelineItem;

    public function deleteItem(int $id): bool;

    // Timeline bounds
    /**
     * @return array{min: string, max: string}
     */
    public function getTimelineBounds(): array;
}
