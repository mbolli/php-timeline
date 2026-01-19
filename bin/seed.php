#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Domain\Model\TimelineGroup;
use App\Domain\Model\TimelineItem;
use App\Infrastructure\Persistence\SqliteTimelineRepository;

$dbPath = __DIR__ . '/../data/timeline.db';
$schemaPath = __DIR__ . '/../data/schema.sql';

// Remove existing database
if (file_exists($dbPath)) {
    unlink($dbPath);
    echo "🗑️  Removed existing database\n";
}

// Create data directory if not exists
if (!is_dir(dirname($dbPath))) {
    mkdir(dirname($dbPath), 0755, true);
}

// Create database and schema
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(file_get_contents($schemaPath));
echo "📄 Created database schema\n";

$repository = new SqliteTimelineRepository($pdo);

// =============================================================================
// Sample Data - Adjust dates and items to match your personal timeline!
// =============================================================================

$groups = [
    // Technology
    [
        'name' => 'Mobile Phones',
        'icon' => '📱',
        'color' => '#3498db',
        'items' => [
            ['title' => 'Nokia 3310', 'start' => '2002-06', 'end' => '2005-03', 'description' => 'The indestructible legend'],
            ['title' => 'Sony Ericsson K750i', 'start' => '2005-03', 'end' => '2007-09', 'description' => '2MP camera phone'],
            ['title' => 'iPhone 3G', 'start' => '2008-07', 'end' => '2010-06', 'description' => 'First iPhone with App Store', 'metadata' => ['storage' => '8GB']],
            ['title' => 'iPhone 4', 'start' => '2010-06', 'end' => '2012-09', 'description' => 'Retina display', 'metadata' => ['storage' => '16GB']],
            ['title' => 'iPhone 5', 'start' => '2012-09', 'end' => '2014-09', 'metadata' => ['storage' => '32GB']],
            ['title' => 'iPhone 6 Plus', 'start' => '2014-09', 'end' => '2016-09', 'description' => 'First phablet', 'metadata' => ['storage' => '64GB']],
            ['title' => 'iPhone X', 'start' => '2017-11', 'end' => '2019-09', 'description' => 'Face ID era begins', 'metadata' => ['storage' => '256GB']],
            ['title' => 'iPhone 12 Pro', 'start' => '2020-10', 'end' => '2023-09', 'description' => '5G, LiDAR', 'metadata' => ['storage' => '256GB']],
            ['title' => 'iPhone 15 Pro Max', 'start' => '2023-09', 'end' => null, 'description' => 'Current daily driver', 'color' => '#27ae60', 'metadata' => ['storage' => '512GB']],
        ],
    ],
    [
        'name' => 'Computers',
        'icon' => '💻',
        'color' => '#9b59b6',
        'items' => [
            ['title' => 'Custom Pentium III', 'start' => '2000-01', 'end' => '2003-06', 'description' => '500MHz, 128MB RAM'],
            ['title' => 'Custom AMD Athlon XP', 'start' => '2003-06', 'end' => '2006-12', 'description' => '2800+, 1GB RAM'],
            ['title' => 'Dell Inspiron', 'start' => '2006-12', 'end' => '2010-08', 'description' => 'First laptop'],
            ['title' => 'MacBook Pro 15"', 'start' => '2010-08', 'end' => '2014-03', 'description' => 'Switch to Mac'],
            ['title' => 'Custom Gaming PC', 'start' => '2014-03', 'end' => '2019-11', 'description' => 'i7-4790K, GTX 970'],
            ['title' => 'MacBook Pro 16" Intel', 'start' => '2019-11', 'end' => '2021-10', 'description' => 'Work laptop'],
            ['title' => 'MacBook Pro 14" M1 Pro', 'start' => '2021-10', 'end' => '2024-01', 'description' => 'Apple Silicon revolution'],
            ['title' => 'MacBook Pro 16" M3 Max', 'start' => '2024-01', 'end' => null, 'description' => 'Current workstation', 'color' => '#27ae60'],
        ],
    ],
    [
        'name' => 'Gaming Consoles',
        'icon' => '🎮',
        'color' => '#e74c3c',
        'items' => [
            ['title' => 'PlayStation 1', 'start' => '1997-12', 'end' => '2001-03', 'description' => 'Final Fantasy VII!'],
            ['title' => 'PlayStation 2', 'start' => '2001-03', 'end' => '2007-11', 'description' => 'Best-selling console ever'],
            ['title' => 'Xbox 360', 'start' => '2007-11', 'end' => '2013-11', 'description' => 'Halo 3 era'],
            ['title' => 'PlayStation 4 Pro', 'start' => '2016-11', 'end' => '2020-11', 'description' => '4K gaming'],
            ['title' => 'PlayStation 5', 'start' => '2020-11', 'end' => null, 'description' => 'Current gen', 'color' => '#27ae60'],
            ['title' => 'Nintendo Switch OLED', 'start' => '2021-10', 'end' => null, 'description' => 'Portable gaming', 'color' => '#27ae60'],
        ],
    ],

    // Vehicles
    [
        'name' => 'Vehicles',
        'icon' => '🚗',
        'color' => '#f39c12',
        'items' => [
            ['title' => 'Honda Civic (1998)', 'start' => '2004-06', 'end' => '2008-04', 'description' => 'First car!'],
            ['title' => 'VW Golf GTI Mk5', 'start' => '2008-04', 'end' => '2012-08', 'description' => 'Hot hatch fun'],
            ['title' => 'BMW 3 Series (E90)', 'start' => '2012-08', 'end' => '2017-03', 'description' => '335i, manual'],
            ['title' => 'Tesla Model 3', 'start' => '2019-06', 'end' => '2023-02', 'description' => 'First EV', 'metadata' => ['range' => '310 miles']],
            ['title' => 'Tesla Model Y LR', 'start' => '2023-02', 'end' => null, 'description' => 'Family EV', 'color' => '#27ae60', 'metadata' => ['range' => '330 miles']],
        ],
    ],

    // Life Events
    [
        'name' => 'Education',
        'icon' => '🎓',
        'color' => '#1abc9c',
        'items' => [
            ['title' => 'Elementary School', 'start' => '1993-09', 'end' => '1999-06'],
            ['title' => 'Middle School', 'start' => '1999-09', 'end' => '2002-06'],
            ['title' => 'High School', 'start' => '2002-09', 'end' => '2006-06', 'description' => 'Go team!'],
            ['title' => 'University - CS Degree', 'start' => '2006-09', 'end' => '2010-05', 'description' => 'Computer Science BS'],
            ['title' => 'Online Courses', 'start' => '2018-01', 'end' => '2019-12', 'description' => 'Machine Learning, Rust'],
        ],
    ],
    [
        'name' => 'Employment',
        'icon' => '💼',
        'color' => '#34495e',
        'items' => [
            ['title' => 'Startup - Junior Dev', 'start' => '2010-07', 'end' => '2012-08', 'description' => 'PHP, MySQL'],
            ['title' => 'Agency - Full Stack', 'start' => '2012-09', 'end' => '2015-06', 'description' => 'Node.js, React'],
            ['title' => 'Tech Corp - Senior Dev', 'start' => '2015-07', 'end' => '2019-02', 'description' => 'Microservices'],
            ['title' => 'Remote - Staff Engineer', 'start' => '2019-03', 'end' => null, 'description' => 'Current role', 'color' => '#27ae60'],
        ],
    ],
    [
        'name' => 'Residences',
        'icon' => '🏠',
        'color' => '#8e44ad',
        'items' => [
            ['title' => 'Parents House', 'start' => '1987-01', 'end' => '2006-08', 'description' => 'Childhood home'],
            ['title' => 'College Dorm', 'start' => '2006-09', 'end' => '2008-05', 'description' => 'Freshman/Sophomore years'],
            ['title' => 'Shared Apartment', 'start' => '2008-06', 'end' => '2012-07', 'description' => 'First apartment with roommates'],
            ['title' => 'Studio Apartment', 'start' => '2012-08', 'end' => '2016-03', 'description' => 'First solo place'],
            ['title' => 'Condo', 'start' => '2016-04', 'end' => '2020-09', 'description' => 'First purchase!'],
            ['title' => 'House', 'start' => '2020-10', 'end' => null, 'description' => 'Current home', 'color' => '#27ae60'],
        ],
    ],

    // Personal
    [
        'name' => 'Relationships',
        'icon' => '❤️',
        'color' => '#e91e63',
        'items' => [
            ['title' => 'Dating', 'start' => '2014-03', 'end' => '2016-08', 'description' => 'Met at a conference'],
            ['title' => 'Engaged', 'start' => '2016-08', 'end' => '2018-06'],
            ['title' => 'Married', 'start' => '2018-06', 'end' => null, 'description' => 'Best day ever!', 'color' => '#27ae60'],
        ],
    ],
    [
        'name' => 'Pets',
        'icon' => '🐾',
        'color' => '#795548',
        'items' => [
            ['title' => 'Max (Golden Retriever)', 'start' => '2015-04', 'end' => '2028-01', 'description' => 'Good boy, RIP 💔'],
            ['title' => 'Luna (Cat)', 'start' => '2019-08', 'end' => null, 'description' => 'Rescue kitty', 'color' => '#27ae60'],
            ['title' => 'Charlie (Labrador)', 'start' => '2022-03', 'end' => null, 'description' => 'Energetic puppy', 'color' => '#27ae60'],
        ],
    ],
    [
        'name' => 'Hobbies & Sports',
        'icon' => '⚽',
        'color' => '#00bcd4',
        'items' => [
            ['title' => 'Soccer', 'start' => '1995-01', 'end' => '2006-06', 'description' => 'Youth league'],
            ['title' => 'Guitar', 'start' => '2005-06', 'end' => null, 'description' => 'Still learning', 'color' => '#27ae60'],
            ['title' => 'Photography', 'start' => '2012-01', 'end' => null, 'description' => 'Landscape & street', 'color' => '#27ae60'],
            ['title' => 'Running', 'start' => '2018-01', 'end' => null, 'description' => 'Completed 2 marathons', 'color' => '#27ae60'],
            ['title' => 'Rock Climbing', 'start' => '2021-06', 'end' => null, 'description' => 'Indoor bouldering', 'color' => '#27ae60'],
        ],
    ],
];

echo "\n🌱 Seeding database...\n\n";

$sortOrder = 0;
foreach ($groups as $groupData) {
    $group = new TimelineGroup(
        id: null,
        name: $groupData['name'],
        icon: $groupData['icon'] ?? null,
        color: $groupData['color'] ?? null,
        sortOrder: $sortOrder++,
    );

    $savedGroup = $repository->saveGroup($group);
    echo "📁 Created group: {$groupData['name']}\n";

    foreach ($groupData['items'] as $itemData) {
        $item = new TimelineItem(
            id: null,
            groupId: $savedGroup->id,
            title: $itemData['title'],
            startDate: $itemData['start'],
            endDate: $itemData['end'] ?? null,
            description: $itemData['description'] ?? null,
            color: $itemData['color'] ?? null,
            metadata: $itemData['metadata'] ?? null,
        );

        $repository->saveItem($item);
        $ongoingIndicator = $item->isOngoing() ? ' (ongoing)' : '';
        echo "   └─ {$itemData['title']}: {$itemData['start']} → " . ($itemData['end'] ?? 'present') . "{$ongoingIndicator}\n";
    }
}

$bounds = $repository->getTimelineBounds();
echo "\n📊 Timeline spans: {$bounds['min']} → {$bounds['max']}\n";

$totalItems = 0;
foreach ($groups as $g) {
    $totalItems += count($g['items']);
}

echo "\n✅ Seeding complete!\n";
echo '   - ' . count($groups) . " groups\n";
echo "   - {$totalItems} items\n";
echo "\n🚀 Run 'php swoole-server.php' to start the server\n";
