<?php

declare(strict_types=1);

use App\Domain\Model\TimelineGroup;
use App\Domain\Model\TimelineItem;
use App\Infrastructure\Persistence\SqliteTimelineRepository;

describe('SqliteTimelineRepository', function (): void {
    beforeEach(function (): void {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schema = file_get_contents(__DIR__ . '/../../data/schema.sql');
        $this->pdo->exec($schema);
        $this->repository = new SqliteTimelineRepository($this->pdo);
    });

    describe('Groups', function (): void {
        it('saves and retrieves a group', function (): void {
            $group = new TimelineGroup(
                id: null,
                name: 'Mobile Phones',
                icon: '📱',
                color: '#3498db',
                sortOrder: 0,
            );

            $saved = $this->repository->saveGroup($group);

            expect($saved->id)->not->toBeNull();
            expect($saved->name)->toBe('Mobile Phones');

            $retrieved = $this->repository->getGroupById($saved->id);
            expect($retrieved)->not->toBeNull();
            expect($retrieved->name)->toBe('Mobile Phones');
        });

        it('updates an existing group', function (): void {
            $group = new TimelineGroup(
                id: null,
                name: 'Original Name',
                icon: '📱',
            );

            $saved = $this->repository->saveGroup($group);

            $updated = new TimelineGroup(
                id: $saved->id,
                name: 'Updated Name',
                icon: '🎮',
                color: '#ff0000',
            );

            $result = $this->repository->saveGroup($updated);

            expect($result->name)->toBe('Updated Name');
            expect($result->icon)->toBe('🎮');
        });

        it('deletes a group', function (): void {
            $group = new TimelineGroup(
                id: null,
                name: 'To Delete',
            );

            $saved = $this->repository->saveGroup($group);
            $result = $this->repository->deleteGroup($saved->id);

            expect($result)->toBeTrue();
            expect($this->repository->getGroupById($saved->id))->toBeNull();
        });

        it('retrieves all groups ordered by sort_order', function (): void {
            $this->repository->saveGroup(new TimelineGroup(null, 'Third', sortOrder: 2));
            $this->repository->saveGroup(new TimelineGroup(null, 'First', sortOrder: 0));
            $this->repository->saveGroup(new TimelineGroup(null, 'Second', sortOrder: 1));

            $groups = $this->repository->getAllGroups();

            expect($groups)->toHaveCount(3);
            expect($groups[0]->name)->toBe('First');
            expect($groups[1]->name)->toBe('Second');
            expect($groups[2]->name)->toBe('Third');
        });

        it('reorders groups', function (): void {
            $g1 = $this->repository->saveGroup(new TimelineGroup(null, 'A', sortOrder: 0));
            $g2 = $this->repository->saveGroup(new TimelineGroup(null, 'B', sortOrder: 1));
            $g3 = $this->repository->saveGroup(new TimelineGroup(null, 'C', sortOrder: 2));

            // Reorder: C, A, B
            $this->repository->reorderGroups([$g3->id, $g1->id, $g2->id]);

            $groups = $this->repository->getAllGroups();

            expect($groups[0]->name)->toBe('C');
            expect($groups[1]->name)->toBe('A');
            expect($groups[2]->name)->toBe('B');
        });
    });

    describe('Items', function (): void {
        beforeEach(function (): void {
            $group = new TimelineGroup(null, 'Test Group');
            $this->group = $this->repository->saveGroup($group);
        });

        it('saves and retrieves an item', function (): void {
            $item = new TimelineItem(
                id: null,
                groupId: $this->group->id,
                title: 'iPhone 15',
                startDate: '2023-09',
                endDate: '2024-09',
            );

            $saved = $this->repository->saveItem($item);

            expect($saved->id)->not->toBeNull();
            expect($saved->title)->toBe('iPhone 15');

            $retrieved = $this->repository->getItemById($saved->id);
            expect($retrieved)->not->toBeNull();
            expect($retrieved->title)->toBe('iPhone 15');
        });

        it('updates an existing item', function (): void {
            $item = new TimelineItem(
                id: null,
                groupId: $this->group->id,
                title: 'Original',
                startDate: '2023-01',
            );

            $saved = $this->repository->saveItem($item);

            $updated = new TimelineItem(
                id: $saved->id,
                groupId: $this->group->id,
                title: 'Updated',
                startDate: '2023-01',
                endDate: '2024-01',
            );

            $result = $this->repository->saveItem($updated);

            expect($result->title)->toBe('Updated');
            expect($result->endDate)->toBe('2024-01');
        });

        it('deletes an item', function (): void {
            $item = new TimelineItem(
                id: null,
                groupId: $this->group->id,
                title: 'To Delete',
                startDate: '2023-01',
            );

            $saved = $this->repository->saveItem($item);
            $result = $this->repository->deleteItem($saved->id);

            expect($result)->toBeTrue();
            expect($this->repository->getItemById($saved->id))->toBeNull();
        });

        it('retrieves items by group', function (): void {
            $group2 = $this->repository->saveGroup(new TimelineGroup(null, 'Other Group'));

            $this->repository->saveItem(new TimelineItem(null, $this->group->id, 'Item 1', '2023-01'));
            $this->repository->saveItem(new TimelineItem(null, $this->group->id, 'Item 2', '2023-06'));
            $this->repository->saveItem(new TimelineItem(null, $group2->id, 'Other Item', '2023-01'));

            $items = $this->repository->getItemsByGroupId($this->group->id);

            expect($items)->toHaveCount(2);
        });

        it('retrieves all groups with items', function (): void {
            $group2 = $this->repository->saveGroup(new TimelineGroup(null, 'Group 2'));

            $this->repository->saveItem(new TimelineItem(null, $this->group->id, 'Item 1', '2023-01'));
            $this->repository->saveItem(new TimelineItem(null, $group2->id, 'Item 2', '2023-01'));
            $this->repository->saveItem(new TimelineItem(null, $group2->id, 'Item 3', '2023-06'));

            $groups = $this->repository->getAllGroupsWithItems();

            expect($groups)->toHaveCount(2);
            expect($groups[0]->items)->toHaveCount(1);
            expect($groups[1]->items)->toHaveCount(2);
        });
    });

    describe('Timeline Bounds', function (): void {
        it('returns timeline bounds', function (): void {
            $group = $this->repository->saveGroup(new TimelineGroup(null, 'Test'));

            $this->repository->saveItem(new TimelineItem(null, $group->id, 'Early', '2020-01', '2021-06'));
            $this->repository->saveItem(new TimelineItem(null, $group->id, 'Late', '2023-01', '2024-12'));

            $bounds = $this->repository->getTimelineBounds();

            expect($bounds['min'])->toBe('2020-01');
            expect($bounds['max'])->toBe('2024-12');
        });

        it('returns current date for empty timeline', function (): void {
            $bounds = $this->repository->getTimelineBounds();

            expect($bounds['min'])->toBe(date('Y-m'));
            expect($bounds['max'])->toBe(date('Y-m'));
        });
    });
});
