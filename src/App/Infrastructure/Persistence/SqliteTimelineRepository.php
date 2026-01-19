<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Model\TimelineGroup;
use App\Domain\Model\TimelineItem;
use App\Domain\Repository\TimelineRepositoryInterface;

final class SqliteTimelineRepository implements TimelineRepositoryInterface {
    public function __construct(
        private readonly \PDO $pdo,
    ) {}

    public function getAllGroups(): array {
        $stmt = $this->pdo->query('SELECT * FROM groups ORDER BY sort_order ASC, id ASC');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn (array $row) => TimelineGroup::fromArray($row), $rows);
    }

    public function getAllGroupsWithItems(): array {
        $groups = $this->getAllGroups();
        $items = $this->getAllItems();

        $itemsByGroup = [];
        foreach ($items as $item) {
            $itemsByGroup[$item->groupId][] = $item;
        }

        return array_map(
            fn (TimelineGroup $group) => $group->withItems($itemsByGroup[$group->id] ?? []),
            $groups
        );
    }

    public function getGroupById(int $id): ?TimelineGroup {
        $stmt = $this->pdo->prepare('SELECT * FROM groups WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? TimelineGroup::fromArray($row) : null;
    }

    public function saveGroup(TimelineGroup $group): TimelineGroup {
        if ($group->id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO groups (name, icon, color, sort_order) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $group->name,
                $group->icon,
                $group->color,
                $group->sortOrder,
            ]);

            $id = (int) $this->pdo->lastInsertId();

            return $this->getGroupById($id);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE groups SET name = ?, icon = ?, color = ?, sort_order = ? WHERE id = ?'
        );
        $stmt->execute([
            $group->name,
            $group->icon,
            $group->color,
            $group->sortOrder,
            $group->id,
        ]);

        return $this->getGroupById($group->id);
    }

    public function deleteGroup(int $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM groups WHERE id = ?');

        return $stmt->execute([$id]);
    }

    /**
     * @param list<int> $orderedIds
     */
    public function reorderGroups(array $orderedIds): bool {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare('UPDATE groups SET sort_order = ? WHERE id = ?');
            foreach ($orderedIds as $order => $id) {
                $stmt->execute([$order, $id]);
            }
            $this->pdo->commit();

            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            return false;
        }
    }

    public function getAllItems(): array {
        $stmt = $this->pdo->query('SELECT * FROM items ORDER BY start_date ASC');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn (array $row) => TimelineItem::fromArray($row), $rows);
    }

    public function getItemsByGroupId(int $groupId): array {
        $stmt = $this->pdo->prepare('SELECT * FROM items WHERE group_id = ? ORDER BY start_date ASC');
        $stmt->execute([$groupId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn (array $row) => TimelineItem::fromArray($row), $rows);
    }

    public function getItemById(int $id): ?TimelineItem {
        $stmt = $this->pdo->prepare('SELECT * FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? TimelineItem::fromArray($row) : null;
    }

    public function saveItem(TimelineItem $item): TimelineItem {
        if ($item->id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO items (group_id, title, description, start_date, end_date, color, metadata)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $item->groupId,
                $item->title,
                $item->description,
                $item->startDate,
                $item->endDate,
                $item->color,
                $item->metadata ? json_encode($item->metadata) : null,
            ]);

            $id = (int) $this->pdo->lastInsertId();

            return $this->getItemById($id);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE items SET group_id = ?, title = ?, description = ?, start_date = ?, end_date = ?, color = ?, metadata = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $item->groupId,
            $item->title,
            $item->description,
            $item->startDate,
            $item->endDate,
            $item->color,
            $item->metadata ? json_encode($item->metadata) : null,
            $item->id,
        ]);

        return $this->getItemById($item->id);
    }

    public function deleteItem(int $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM items WHERE id = ?');

        return $stmt->execute([$id]);
    }

    /**
     * @return array{min: string, max: string}
     */
    public function getTimelineBounds(): array {
        $stmt = $this->pdo->query(
            'SELECT MIN(start_date) as min_date, MAX(COALESCE(end_date, start_date)) as max_date FROM items'
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $now = date('Y-m');

        return [
            'min' => $row['min_date'] ?? $now,
            'max' => $row['max_date'] ?? $now,
        ];
    }
}
