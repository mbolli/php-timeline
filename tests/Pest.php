<?php

use App\Infrastructure\EventBus\SwooleEventBus;
use App\Infrastructure\Persistence\SqliteTimelineRepository;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses()
    ->beforeEach(function (): void {
        // Setup test database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Load schema
        $schema = file_get_contents(__DIR__ . '/../data/schema.sql');
        $this->pdo->exec($schema);
    })
    ->in('Feature', 'Unit')
;

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidTimelineItem', fn () => $this
    ->toHaveProperty('id')
    ->toHaveProperty('groupId')
    ->toHaveProperty('title')
    ->toHaveProperty('startDate'));

expect()->extend('toBeValidTimelineGroup', fn () => $this
    ->toHaveProperty('id')
    ->toHaveProperty('name')
    ->toHaveProperty('icon')
    ->toHaveProperty('color'));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function createTestRepository(): SqliteTimelineRepository {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $schema = file_get_contents(__DIR__ . '/../data/schema.sql');
    $pdo->exec($schema);

    return new SqliteTimelineRepository($pdo);
}

function createTestEventBus(): SwooleEventBus {
    return new SwooleEventBus();
}
