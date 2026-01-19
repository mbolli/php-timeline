<?php

declare(strict_types=1);

use App\Domain\Model\TimelineGroup;
use App\Domain\Model\TimelineItem;

describe('TimelineGroup', function (): void {
    it('creates a group from array', function (): void {
        $data = [
            'id' => 1,
            'name' => 'Mobile Phones',
            'icon' => '📱',
            'color' => '#3498db',
            'sort_order' => 0,
        ];

        $group = TimelineGroup::fromArray($data);

        expect($group)
            ->id->toBe(1)
            ->name->toBe('Mobile Phones')
            ->icon->toBe('📱')
            ->color->toBe('#3498db')
            ->sortOrder->toBe(0)
        ;
    });

    it('has default values', function (): void {
        $group = TimelineGroup::fromArray([
            'name' => 'Test Group',
        ]);

        expect($group)
            ->icon->toBe('📁')
            ->color->toBe('#3498db')
            ->sortOrder->toBe(0)
            ->items->toBe([])
        ;
    });

    it('can be created with items', function (): void {
        $items = [
            new TimelineItem(
                id: 1,
                groupId: 1,
                title: 'Item 1',
                startDate: '2023-01',
            ),
            new TimelineItem(
                id: 2,
                groupId: 1,
                title: 'Item 2',
                startDate: '2023-06',
            ),
        ];

        $group = new TimelineGroup(
            id: 1,
            name: 'Test Group',
            items: $items,
        );

        expect($group->items)->toHaveCount(2);
    });

    it('creates new instance with items using withItems', function (): void {
        $group = new TimelineGroup(
            id: 1,
            name: 'Test Group',
        );

        expect($group->items)->toBeEmpty();

        $items = [
            new TimelineItem(
                id: 1,
                groupId: 1,
                title: 'Item 1',
                startDate: '2023-01',
            ),
        ];

        $groupWithItems = $group->withItems($items);

        expect($group->items)->toBeEmpty(); // Original unchanged
        expect($groupWithItems->items)->toHaveCount(1);
        expect($groupWithItems->name)->toBe('Test Group'); // Other props preserved
    });

    it('converts to array', function (): void {
        $group = new TimelineGroup(
            id: 1,
            name: 'Test',
            icon: '🎮',
            color: '#ff0000',
            sortOrder: 5,
        );

        $array = $group->toArray();

        expect($array)
            ->toHaveKey('id', 1)
            ->toHaveKey('name', 'Test')
            ->toHaveKey('icon', '🎮')
            ->toHaveKey('color', '#ff0000')
            ->toHaveKey('sort_order', 5)
        ;
    });
});
