<?php

declare(strict_types=1);

use App\Application\Command\CreateGroup\CreateGroupHandler;
use App\Application\Command\CreateItem\CreateItemHandler;
use App\Application\Command\DeleteGroup\DeleteGroupHandler;
use App\Application\Command\DeleteItem\DeleteItemHandler;
use App\Application\Command\UpdateGroup\UpdateGroupHandler;
use App\Application\Command\UpdateItem\UpdateItemHandler;
use App\Domain\Model\TimelineGroup;
use App\Domain\Model\TimelineItem;
use App\Infrastructure\EventBus\SwooleEventBus;
use App\Infrastructure\Http\Handler\Command\GroupCommandHandler;
use App\Infrastructure\Http\Handler\Command\ItemCommandHandler;
use App\Infrastructure\Persistence\SqliteTimelineRepository;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;

describe('HTTP Command Handlers', function (): void {
    beforeEach(function (): void {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schema = file_get_contents(__DIR__ . '/../../data/schema.sql');
        $this->pdo->exec($schema);
        $this->repository = new SqliteTimelineRepository($this->pdo);
        $this->eventBus = new SwooleEventBus();

        // Create command handlers
        $this->createGroupHandler = new CreateGroupHandler($this->repository, $this->eventBus);
        $this->updateGroupHandler = new UpdateGroupHandler($this->repository, $this->eventBus);
        $this->deleteGroupHandler = new DeleteGroupHandler($this->repository, $this->eventBus);
        $this->createItemHandler = new CreateItemHandler($this->repository, $this->eventBus);
        $this->updateItemHandler = new UpdateItemHandler($this->repository, $this->eventBus);
        $this->deleteItemHandler = new DeleteItemHandler($this->repository, $this->eventBus);

        // Create HTTP handlers
        $this->groupCommandHandler = new GroupCommandHandler(
            $this->createGroupHandler,
            $this->updateGroupHandler,
            $this->deleteGroupHandler,
            $this->repository,
        );

        $this->itemCommandHandler = new ItemCommandHandler(
            $this->createItemHandler,
            $this->updateItemHandler,
            $this->deleteItemHandler,
        );

        // Create a default group for item tests
        $this->group = $this->repository->saveGroup(new TimelineGroup(null, 'Test Group'));
    });

    describe('GroupCommandHandler', function (): void {
        describe('create', function (): void {
            it('creates a group via HTTP request', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('POST')
                    ->withUri(new Uri('/cmd/groups'))
                    ->withParsedBody([
                        'name' => 'Mobile Phones',
                        'icon' => '📱',
                        'color' => '#e74c3c',
                    ]);

                $response = $this->groupCommandHandler->create($request);

                expect($response->getStatusCode())->toBe(204);

                $groups = $this->repository->getAllGroups();
                expect($groups)->toHaveCount(2);
                expect($groups[1]->name)->toBe('Mobile Phones');
                expect($groups[1]->icon)->toBe('📱');
                expect($groups[1]->color)->toBe('#e74c3c');
            });

            it('creates a group with Datastar wrapped payload', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('POST')
                    ->withUri(new Uri('/cmd/groups'))
                    ->withParsedBody([
                        'datastar' => [
                            'name' => 'Gaming Consoles',
                            'icon' => '🎮',
                            'color' => '#9b59b6',
                        ],
                    ]);

                $response = $this->groupCommandHandler->create($request);

                expect($response->getStatusCode())->toBe(204);

                $groups = $this->repository->getAllGroups();
                expect($groups[1]->name)->toBe('Gaming Consoles');
            });

            it('returns 400 when name is missing', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('POST')
                    ->withUri(new Uri('/cmd/groups'))
                    ->withParsedBody([
                        'icon' => '📱',
                    ]);

                $response = $this->groupCommandHandler->create($request);

                expect($response->getStatusCode())->toBe(400);
            });
        });

        describe('update', function (): void {
            it('updates a group via HTTP request', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('PUT')
                    ->withUri(new Uri('/cmd/groups/' . $this->group->id))
                    ->withAttribute('id', (string) $this->group->id)
                    ->withParsedBody([
                        'name' => 'Updated Group Name',
                        'icon' => '🚀',
                        'color' => '#2ecc71',
                    ]);

                $response = $this->groupCommandHandler->update($request);

                expect($response->getStatusCode())->toBe(204);

                $group = $this->repository->getGroupById($this->group->id);
                expect($group->name)->toBe('Updated Group Name');
                expect($group->icon)->toBe('🚀');
                expect($group->color)->toBe('#2ecc71');
            });

            it('returns 404 for non-existent group', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('PUT')
                    ->withUri(new Uri('/cmd/groups/99999'))
                    ->withAttribute('id', '99999')
                    ->withParsedBody([
                        'name' => 'Non-existent',
                    ]);

                $response = $this->groupCommandHandler->update($request);

                expect($response->getStatusCode())->toBe(404);
            });
        });

        describe('delete', function (): void {
            it('deletes a group via HTTP request', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('DELETE')
                    ->withUri(new Uri('/cmd/groups/' . $this->group->id))
                    ->withAttribute('id', (string) $this->group->id);

                $response = $this->groupCommandHandler->delete($request);

                expect($response->getStatusCode())->toBe(204);
                expect($this->repository->getGroupById($this->group->id))->toBeNull();
            });
        });

        describe('reorder', function (): void {
            it('reorders groups via HTTP request', function (): void {
                $group2 = $this->repository->saveGroup(new TimelineGroup(null, 'Second Group'));
                $group3 = $this->repository->saveGroup(new TimelineGroup(null, 'Third Group'));

                $request = (new ServerRequest())
                    ->withMethod('PUT')
                    ->withUri(new Uri('/cmd/groups/reorder'))
                    ->withParsedBody([
                        'orderedIds' => [$group3->id, $group2->id, $this->group->id],
                    ]);

                $response = $this->groupCommandHandler->reorder($request);

                expect($response->getStatusCode())->toBe(204);

                $groups = $this->repository->getAllGroups();
                expect($groups[0]->id)->toBe($group3->id);
                expect($groups[1]->id)->toBe($group2->id);
                expect($groups[2]->id)->toBe($this->group->id);
            });

            it('returns 400 when orderedIds is missing', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('PUT')
                    ->withUri(new Uri('/cmd/groups/reorder'))
                    ->withParsedBody([]);

                $response = $this->groupCommandHandler->reorder($request);

                expect($response->getStatusCode())->toBe(400);
            });
        });
    });

    describe('ItemCommandHandler', function (): void {
        describe('create', function (): void {
            it('creates an item via HTTP request', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('POST')
                    ->withUri(new Uri('/cmd/items'))
                    ->withParsedBody([
                        'groupId' => $this->group->id,
                        'title' => 'iPhone 15 Pro',
                        'startDate' => '2023-09',
                        'endDate' => '2024-09',
                        'color' => '#3498db',
                        'description' => 'My daily driver',
                    ]);

                $response = $this->itemCommandHandler->create($request);

                expect($response->getStatusCode())->toBe(204);

                $items = $this->repository->getAllItems();
                expect($items)->toHaveCount(1);
                expect($items[0]->title)->toBe('iPhone 15 Pro');
                expect($items[0]->groupId)->toBe($this->group->id);
                expect($items[0]->startDate)->toBe('2023-09');
                expect($items[0]->endDate)->toBe('2024-09');
            });

            it('creates an item with Datastar wrapped payload', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('POST')
                    ->withUri(new Uri('/cmd/items'))
                    ->withParsedBody([
                        'datastar' => [
                            'groupId' => $this->group->id,
                            'title' => 'MacBook Pro',
                            'startDate' => '2022-01',
                        ],
                    ]);

                $response = $this->itemCommandHandler->create($request);

                expect($response->getStatusCode())->toBe(204);

                $items = $this->repository->getAllItems();
                expect($items[0]->title)->toBe('MacBook Pro');
            });

            it('creates an ongoing item without end date', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('POST')
                    ->withUri(new Uri('/cmd/items'))
                    ->withParsedBody([
                        'groupId' => $this->group->id,
                        'title' => 'Current Job',
                        'startDate' => '2024-01',
                    ]);

                $response = $this->itemCommandHandler->create($request);

                expect($response->getStatusCode())->toBe(204);

                $items = $this->repository->getAllItems();
                expect($items[0]->isOngoing())->toBeTrue();
            });

            it('returns 400 when required fields are missing', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('POST')
                    ->withUri(new Uri('/cmd/items'))
                    ->withParsedBody([
                        'title' => 'Missing group and date',
                    ]);

                $response = $this->itemCommandHandler->create($request);

                expect($response->getStatusCode())->toBe(400);
            });
        });

        describe('update', function (): void {
            beforeEach(function (): void {
                $this->item = $this->repository->saveItem(new TimelineItem(
                    id: null,
                    groupId: $this->group->id,
                    title: 'Original Title',
                    startDate: '2023-01',
                    endDate: '2023-12',
                ));
            });

            it('updates an item via HTTP request', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('PUT')
                    ->withUri(new Uri('/cmd/items/' . $this->item->id))
                    ->withAttribute('id', (string) $this->item->id)
                    ->withParsedBody([
                        'groupId' => $this->group->id,
                        'title' => 'Updated Title',
                        'startDate' => '2023-03',
                        'endDate' => '2024-06',
                        'color' => '#e74c3c',
                        'description' => 'Updated description',
                    ]);

                $response = $this->itemCommandHandler->update($request);

                expect($response->getStatusCode())->toBe(204);

                $item = $this->repository->getItemById($this->item->id);
                expect($item->title)->toBe('Updated Title');
                expect($item->startDate)->toBe('2023-03');
                expect($item->endDate)->toBe('2024-06');
                expect($item->color)->toBe('#e74c3c');
                expect($item->description)->toBe('Updated description');
            });

            it('can change item to ongoing by clearing end date', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('PUT')
                    ->withUri(new Uri('/cmd/items/' . $this->item->id))
                    ->withAttribute('id', (string) $this->item->id)
                    ->withParsedBody([
                        'groupId' => $this->group->id,
                        'title' => 'Now Ongoing',
                        'startDate' => '2023-01',
                        'endDate' => '',
                    ]);

                $response = $this->itemCommandHandler->update($request);

                expect($response->getStatusCode())->toBe(204);

                $item = $this->repository->getItemById($this->item->id);
                expect($item->isOngoing())->toBeTrue();
            });

            it('returns 404 for non-existent item', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('PUT')
                    ->withUri(new Uri('/cmd/items/99999'))
                    ->withAttribute('id', '99999')
                    ->withParsedBody([
                        'groupId' => $this->group->id,
                        'title' => 'Non-existent',
                        'startDate' => '2023-01',
                    ]);

                $response = $this->itemCommandHandler->update($request);

                expect($response->getStatusCode())->toBe(404);
            });

            it('returns 400 when required fields are missing', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('PUT')
                    ->withUri(new Uri('/cmd/items/' . $this->item->id))
                    ->withAttribute('id', (string) $this->item->id)
                    ->withParsedBody([
                        'title' => 'Missing required fields',
                    ]);

                $response = $this->itemCommandHandler->update($request);

                expect($response->getStatusCode())->toBe(400);
            });
        });

        describe('delete', function (): void {
            beforeEach(function (): void {
                $this->item = $this->repository->saveItem(new TimelineItem(
                    id: null,
                    groupId: $this->group->id,
                    title: 'To Be Deleted',
                    startDate: '2023-01',
                ));
            });

            it('deletes an item via HTTP request', function (): void {
                $request = (new ServerRequest())
                    ->withMethod('DELETE')
                    ->withUri(new Uri('/cmd/items/' . $this->item->id))
                    ->withAttribute('id', (string) $this->item->id);

                $response = $this->itemCommandHandler->delete($request);

                expect($response->getStatusCode())->toBe(204);
                expect($this->repository->getItemById($this->item->id))->toBeNull();
            });
        });
    });
});
