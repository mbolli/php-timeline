<?php

declare(strict_types=1);

use App\Application\Command\CreateGroup\CreateGroupCommand;
use App\Application\Command\CreateGroup\CreateGroupHandler;
use App\Application\Command\CreateItem\CreateItemCommand;
use App\Application\Command\CreateItem\CreateItemHandler;
use App\Application\Command\DeleteGroup\DeleteGroupCommand;
use App\Application\Command\DeleteGroup\DeleteGroupHandler;
use App\Application\Command\DeleteItem\DeleteItemCommand;
use App\Application\Command\DeleteItem\DeleteItemHandler;
use App\Application\Command\UpdateGroup\UpdateGroupCommand;
use App\Application\Command\UpdateGroup\UpdateGroupHandler;
use App\Application\Command\UpdateItem\UpdateItemCommand;
use App\Application\Command\UpdateItem\UpdateItemHandler;
use App\Domain\Event\TimelineChangedEvent;
use App\Domain\Model\TimelineGroup;
use App\Domain\Model\TimelineItem;
use App\Infrastructure\EventBus\SwooleEventBus;
use App\Infrastructure\Persistence\SqliteTimelineRepository;

describe('Command Handlers', function (): void {
    beforeEach(function (): void {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schema = file_get_contents(__DIR__ . '/../../data/schema.sql');
        $this->pdo->exec($schema);
        $this->repository = new SqliteTimelineRepository($this->pdo);
        $this->eventBus = new SwooleEventBus();
        $this->receivedEvents = [];
        $this->eventBus->subscribe(function ($event): void {
            $this->receivedEvents[] = $event;
        });

        // Create a default group for item tests
        $this->group = $this->repository->saveGroup(new TimelineGroup(null, 'Test Group'));
    });

    describe('CreateGroupHandler', function (): void {
        it('creates a group and emits event', function (): void {
            $handler = new CreateGroupHandler($this->repository, $this->eventBus);
            $command = new CreateGroupCommand(
                name: 'Mobile Phones',
                icon: '📱',
                color: '#3498db',
                sortOrder: 0,
            );

            $group = $handler($command);

            expect($group->id)->not->toBeNull();
            expect($group->name)->toBe('Mobile Phones');
            expect($this->receivedEvents)->toHaveCount(1);
            expect($this->receivedEvents[0])->toBeInstanceOf(TimelineChangedEvent::class);
        });
    });

    describe('UpdateGroupHandler', function (): void {
        it('updates a group and emits event', function (): void {
            $handler = new UpdateGroupHandler($this->repository, $this->eventBus);
            $command = new UpdateGroupCommand(
                id: $this->group->id,
                name: 'Updated Group',
                icon: '🎮',
            );

            $group = $handler($command);

            expect($group->name)->toBe('Updated Group');
            expect($group->icon)->toBe('🎮');
            expect($this->receivedEvents)->toHaveCount(1);
        });

        it('returns null for non-existent group', function (): void {
            $handler = new UpdateGroupHandler($this->repository, $this->eventBus);
            $command = new UpdateGroupCommand(
                id: 99999,
                name: 'Non-existent',
            );

            expect($handler($command))->toBeNull();
        });
    });

    describe('DeleteGroupHandler', function (): void {
        it('deletes a group and emits event', function (): void {
            $handler = new DeleteGroupHandler($this->repository, $this->eventBus);
            $command = new DeleteGroupCommand(id: $this->group->id);

            $result = $handler($command);

            expect($result)->toBeTrue();
            expect($this->repository->getGroupById($this->group->id))->toBeNull();
            expect($this->receivedEvents)->toHaveCount(1);
        });
    });

    describe('CreateItemHandler', function (): void {
        it('creates an item and emits event', function (): void {
            $handler = new CreateItemHandler($this->repository, $this->eventBus);
            $command = new CreateItemCommand(
                groupId: $this->group->id,
                title: 'iPhone 15 Pro',
                startDate: '2023-09',
                endDate: '2024-09',
                description: 'My current phone',
                color: '#3498db',
                metadata: ['storage' => '256GB'],
            );

            $item = $handler($command);

            expect($item->id)->not->toBeNull();
            expect($item->title)->toBe('iPhone 15 Pro');
            expect($item->description)->toBe('My current phone');
            expect($item->metadata)->toBe(['storage' => '256GB']);
            expect($this->receivedEvents)->toHaveCount(1);
        });
    });

    describe('UpdateItemHandler', function (): void {
        beforeEach(function (): void {
            $item = new TimelineItem(
                id: null,
                groupId: $this->group->id,
                title: 'Original Item',
                startDate: '2023-01',
            );
            $this->item = $this->repository->saveItem($item);
        });

        it('updates an item and emits event', function (): void {
            $handler = new UpdateItemHandler($this->repository, $this->eventBus);
            $command = new UpdateItemCommand(
                id: $this->item->id,
                groupId: $this->group->id,
                title: 'Updated Item',
                startDate: '2023-01',
                endDate: '2024-06',
            );

            $item = $handler($command);

            expect($item->title)->toBe('Updated Item');
            expect($item->endDate)->toBe('2024-06');
            expect($this->receivedEvents)->toHaveCount(1);
        });

        it('returns null for non-existent item', function (): void {
            $handler = new UpdateItemHandler($this->repository, $this->eventBus);
            $command = new UpdateItemCommand(
                id: 99999,
                groupId: $this->group->id,
                title: 'Non-existent',
                startDate: '2023-01',
            );

            expect($handler($command))->toBeNull();
        });
    });

    describe('DeleteItemHandler', function (): void {
        beforeEach(function (): void {
            $item = new TimelineItem(
                id: null,
                groupId: $this->group->id,
                title: 'To Delete',
                startDate: '2023-01',
            );
            $this->item = $this->repository->saveItem($item);
        });

        it('deletes an item and emits event', function (): void {
            $handler = new DeleteItemHandler($this->repository, $this->eventBus);
            $command = new DeleteItemCommand(id: $this->item->id);

            $result = $handler($command);

            expect($result)->toBeTrue();
            expect($this->repository->getItemById($this->item->id))->toBeNull();
            expect($this->receivedEvents)->toHaveCount(1);
        });
    });
});
