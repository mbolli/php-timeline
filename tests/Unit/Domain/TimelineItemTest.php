<?php

declare(strict_types=1);

use App\Domain\Model\TimelineItem;

describe('TimelineItem', function (): void {
    it('creates an item from array', function (): void {
        $data = [
            'id' => 1,
            'group_id' => 1,
            'title' => 'iPhone 15 Pro',
            'start_date' => '2023-09',
            'end_date' => '2024-09',
            'description' => 'My daily driver',
            'color' => '#3498db',
        ];

        $item = TimelineItem::fromArray($data);

        expect($item)
            ->id->toBe(1)
            ->groupId->toBe(1)
            ->title->toBe('iPhone 15 Pro')
            ->startDate->toBe('2023-09')
            ->endDate->toBe('2024-09')
            ->description->toBe('My daily driver')
            ->color->toBe('#3498db')
        ;
    });

    it('detects ongoing items', function (): void {
        $ongoing = TimelineItem::fromArray([
            'group_id' => 1,
            'title' => 'Current Phone',
            'start_date' => '2024-01',
            'end_date' => null,
        ]);

        $completed = TimelineItem::fromArray([
            'group_id' => 1,
            'title' => 'Old Phone',
            'start_date' => '2020-01',
            'end_date' => '2023-12',
        ]);

        expect($ongoing->isOngoing())->toBeTrue();
        expect($completed->isOngoing())->toBeFalse();
    });

    it('calculates duration in months', function (): void {
        $item = TimelineItem::fromArray([
            'group_id' => 1,
            'title' => 'Test Item',
            'start_date' => '2023-01',
            'end_date' => '2024-01',
        ]);

        expect($item->getDurationMonths())->toBe(12);
    });

    it('returns null duration for ongoing items', function (): void {
        $item = TimelineItem::fromArray([
            'group_id' => 1,
            'title' => 'Ongoing Item',
            'start_date' => '2023-01',
            'end_date' => null,
        ]);

        expect($item->getDurationMonths())->toBeNull();
    });

    it('converts to array', function (): void {
        $item = new TimelineItem(
            id: 1,
            groupId: 2,
            title: 'Test',
            startDate: '2023-01',
            endDate: '2024-01',
            description: 'Description',
            color: '#ff0000',
        );

        $array = $item->toArray();

        expect($array)
            ->toHaveKey('id', 1)
            ->toHaveKey('group_id', 2)
            ->toHaveKey('title', 'Test')
            ->toHaveKey('start_date', '2023-01')
            ->toHaveKey('end_date', '2024-01')
        ;
    });
});
