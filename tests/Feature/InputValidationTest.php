<?php

declare(strict_types=1);

use App\Application\Command\CreateGroup\CreateGroupHandler;
use App\Application\Command\CreateItem\CreateItemHandler;
use App\Application\Command\DeleteGroup\DeleteGroupHandler;
use App\Application\Command\DeleteItem\DeleteItemHandler;
use App\Application\Command\ReorderGroups\ReorderGroupsHandler;
use App\Application\Command\ResizeItem\ResizeItemHandler;
use App\Application\Command\UpdateGroup\UpdateGroupHandler;
use App\Application\Command\UpdateItem\UpdateItemHandler;
use App\Domain\Model\TimelineGroup;
use App\Domain\Model\TimelineItem;
use App\Infrastructure\EventBus\SwooleEventBus;
use App\Infrastructure\Http\Handler\Command\GroupCommandHandler;
use App\Infrastructure\Http\Handler\Command\ItemCommandHandler;
use App\Infrastructure\Http\TimelineInput;
use App\Infrastructure\Persistence\SqliteConnection;
use App\Infrastructure\Persistence\SqliteTimelineRepository;
use Laminas\Diactoros\ServerRequest;

beforeEach(function (): void {
    $this->pdo = SqliteConnection::open('sqlite::memory:', __DIR__ . '/../../data/schema.sql');
    $this->repository = new SqliteTimelineRepository($this->pdo);
    $events = new SwooleEventBus();
    $this->items = new ItemCommandHandler(
        new CreateItemHandler($this->repository, $events),
        new UpdateItemHandler($this->repository, $events),
        new DeleteItemHandler($this->repository, $events),
        new ResizeItemHandler($this->repository, $events),
        $this->repository,
    );
    $this->groups = new GroupCommandHandler(
        new CreateGroupHandler($this->repository, $events),
        new UpdateGroupHandler($this->repository, $events),
        new DeleteGroupHandler($this->repository, $events),
        new ReorderGroupsHandler($this->repository, $events),
    );
    $this->group = $this->repository->saveGroup(new TimelineGroup(null, 'Phones'));
});

function postItem(ItemCommandHandler $handler, array $body): array {
    $response = $handler->create((new ServerRequest())->withMethod('POST')->withParsedBody($body));

    return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
}

it('accepts a valid item and normalizes it', function (): void {
    [$data, $errors] = TimelineInput::item(['title' => '  Nokia  ', 'groupId' => '1', 'startDate' => '2001-03', 'endDate' => '', 'color' => ''], fn (): bool => true);

    expect($errors)->toBe([])
        ->and($data['title'])->toBe('Nokia')
        ->and($data['endDate'])->toBeNull()
        ->and($data['color'])->toBeNull()
    ;
});

it('rejects invalid items with a reason per field', function (array $body, string $field): void {
    [$status, $json] = postItem($this->items, $body + ['title' => 'X', 'groupId' => $this->group->id, 'startDate' => '2026-01']);

    expect($status)->toBe(422)
        ->and($json['fields'])->toHaveKey($field)
        ->and($this->repository->getAllItems())->toBe([])
    ;
})->with([
    'blank title' => [['title' => '   '], 'title'],
    'garbage start' => [['startDate' => 'abc'], 'startDate'],
    'month 13' => [['startDate' => '2026-13'], 'startDate'],
    'end before start' => [['startDate' => '2026-06', 'endDate' => '2025-01'], 'endDate'],
    'css in color' => [['color' => 'red;outline:6px solid lime'], 'color'],
    'unknown group' => [['groupId' => 999], 'groupId'],
]);

it('rejects a resize that ends before it starts', function (): void {
    $item = $this->repository->saveItem(new TimelineItem(null, $this->group->id, 'Nokia', '2001-01', '2002-01'));
    $request = (new ServerRequest())->withMethod('PATCH')->withAttribute('id', $item->id)
        ->withParsedBody(['startDate' => '2003-01', 'endDate' => '2002-01', 'groupId' => (string) $this->group->id])
    ;

    expect($this->items->resize($request)->getStatusCode())->toBe(422)
        ->and($this->repository->getItemById($item->id)?->startDate)->toBe('2001-01')
    ;
});

it('rejects a group with an injected color or a blank name', function (): void {
    $create = fn (array $body): int => $this->groups->create((new ServerRequest())->withMethod('POST')->withParsedBody($body))->getStatusCode();

    expect($create(['name' => 'Cars', 'color' => '#00ff00;x:y']))->toBe(422)
        ->and($create(['name' => '  ', 'color' => '#00ff00']))->toBe(422)
        ->and($create(['name' => 'Cars', 'color' => '#00ff00']))->toBe(204)
    ;
});

it('deletes the items of a deleted group', function (): void {
    $this->repository->saveItem(new TimelineItem(null, $this->group->id, 'Nokia', '2001-01'));
    $this->repository->deleteGroup($this->group->id);

    expect((int) $this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn())->toBe(0);
});

it('removes orphaned items when the database is opened', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'timeline');
    $pdo = SqliteConnection::open('sqlite:' . $file, __DIR__ . '/../../data/schema.sql');
    // Before this fix foreign keys were off, which let orphans in
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->exec("INSERT INTO groups (name) VALUES ('Old')");
    $pdo->exec("INSERT INTO items (group_id, title, start_date) VALUES (1, 'Kept', '2001-01'), (2, 'Orphan', '2001-01')");

    $reopened = SqliteConnection::open('sqlite:' . $file, __DIR__ . '/../../data/schema.sql');
    $titles = $reopened->query('SELECT title FROM items')->fetchAll(PDO::FETCH_COLUMN);
    unlink($file);

    expect($titles)->toBe(['Kept']);
});
