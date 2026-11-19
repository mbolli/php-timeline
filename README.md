# Life Timeline

A full-screen horizontal timeline application for visualizing life events, inspired by Google Sheets timeline view and Adobe Premiere's track-based layout.

This project can serve as a **template for building high-performance PHP applications** using Swoole and Mezzio. It demonstrates how to leverage PSR-7/PSR-15 middleware, PSR-11 dependency injection, real-time SSE streaming, and CQRS+event bus patterns—all running on a persistent Swoole HTTP server for maximum throughput.

[![PHP](https://img.shields.io/badge/PHP-8.4+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Swoole](https://img.shields.io/badge/Swoole-6.0+-007EC6?logo=swoole&logoColor=white)](https://www.swoole.com/)
[![OpenSwoole](https://img.shields.io/badge/OpenSwoole-26+-007EC6)](https://openswoole.com/)
[![Datastar](https://img.shields.io/badge/Datastar-1.0.5-FF6B35?logo=rocket&logoColor=white)](https://data-star.dev/)
[![TypeScript](https://img.shields.io/badge/TypeScript-7-3178C6?logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![SQLite](https://img.shields.io/badge/SQLite-3-003B57?logo=sqlite&logoColor=white)](https://www.sqlite.org/)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![Made by zweiundeins.gmbh](https://img.shields.io/badge/Made%20with%20%E2%98%95%20by-zweiundeins.gmbh-blue)](https://zweiundeins.gmbh)

## Features

- **Horizontal Timeline** — Zoomable month/year grid similar to Google Sheets
- **Vertical Grouping** — Track-based layout like Adobe Premiere for categorizing events
- **Resize Handles** — Drag item edges to adjust start/end dates
- **Drag & Drop Reordering** — Reorder groups via drag handle
- **Real-time Multiplayer** — SSE-based updates via Event Bus pattern
- **Declarative Frontend** — [Datastar](https://data-star.dev/) for reactive UI with minimal JavaScript
- **CQRS Architecture** — Separated command and query handlers
- **High Performance** — Swoole HTTP server with native coroutine support

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.4+, Mezzio 3.29, mezzio-swoole 4.14, Swoole 6.0+ or OpenSwoole 26+, datastar-php 1.0.3 |
| Database | SQLite (PDO) |
| Frontend | TypeScript 7, esbuild 0.28, Datastar 1.0.5, pnpm |
| Testing | Pest 5 (PHPUnit 13) |
| Analysis | PHPStan 2.3, PHP-CS-Fixer 3.95 |

## Requirements

- PHP 8.4+
- Swoole 6 extension (`pecl install swoole`) or OpenSwoole 26+
- Node.js 18+ and pnpm (for frontend build)
- SQLite3

mezzio-swoole requires `ext-swoole`, which Composer does not see when OpenSwoole is installed. With OpenSwoole, run Composer with `--ignore-platform-req=ext-swoole`. Add `--ignore-platform-req=ext-inotify` when the inotify extension for hot code reloading is not installed. OpenSwoole also lacks `swoole_set_process_name()`; `config/container.php` falls back to `cli_set_process_title()` in that case.

## Installation

```bash
# Clone the repository
git clone https://github.com/mbolli/php-timeline.git
cd php-timeline

# Install PHP dependencies
composer install

# Install Node dependencies
pnpm install

# Build frontend assets
pnpm build

# Copy environment config (for development)
cp config/autoload/local.php.dist config/autoload/local.php

# Seed the database with sample data
composer db:seed

# Start the server
composer serve
```

Then open http://localhost:3100 in your browser.

### Configuration Files

The `config/autoload/` directory uses Laminas config aggregation:

| File | Purpose |
|------|--------|
| `app.global.php` | Base config for all environments (committed) |
| `local.php.dist` | Development template → copy to `local.php` |
| `production.local.php.dist` | Production template → copy to `production.local.php` |

Files ending in `.local.php` are gitignored and override settings from `app.global.php`.

For **production**, copy and adjust:
```bash
cp config/autoload/production.local.php.dist config/autoload/production.local.php
```

## Development

### Available Scripts

```bash
# PHP
composer serve      # Start Swoole server (port 8080)
composer test       # Run Pest tests
composer stan       # Run PHPStan static analysis
composer cs         # Check code style (dry-run)
composer cs:fix     # Fix code style

# Database
composer db:seed    # Seed database with sample data; refuses to overwrite an existing one
composer db:seed -- --force   # Replace an existing database with the sample data

# Frontend
pnpm build          # Build TypeScript (minified)
pnpm watch          # Watch mode for development
pnpm typecheck      # Type-check without emitting
pnpm test:e2e       # Playwright end-to-end tests
```

### End-to-end tests

`pnpm test:e2e` runs the Playwright tests in `tests/e2e/`. They start their own server on port 3190
with a freshly seeded database in the temp directory, so `data/timeline.db` and a running dev server
stay untouched. The tests use the Chromium at `/usr/bin/chromium`; no browser is downloaded.

| Variable | Meaning |
|----------|---------|
| `E2E_PORT` | Port of the test server (default 3190) |
| `E2E_CHROMIUM` | Path of the Chromium binary |

The server itself reads three variables, which win over the config files:
`TIMELINE_PORT`, `TIMELINE_DB` (path of the SQLite file) and `TIMELINE_PID_FILE`.
`php bin/seed.php <file>` seeds another database file than the default.

### Project Structure

```
├── bin/
│   └── seed.php                 # Database seeder
├── config/
│   ├── autoload/                # Environment configs
│   ├── config.php               # Main config aggregator
│   ├── container.php            # DI container
│   ├── pipeline.php             # Middleware pipeline
│   └── routes.php               # Route definitions
├── data/
│   ├── schema.sql               # SQLite schema
│   └── timeline.db              # SQLite database (generated)
├── public/
│   ├── css/timeline.css         # Timeline styles
│   └── js/app.js                # Compiled TypeScript (generated)
├── src/
│   └── App/
│       ├── Application/
│       │   ├── Command/         # CQRS Commands & Handlers
│       │   └── Query/           # CQRS Queries & Handlers
│       ├── Domain/
│       │   ├── Event/           # Domain events
│       │   ├── Model/           # Domain models
│       │   └── Repository/      # Repository interfaces
│       └── Infrastructure/
│           ├── EventBus/        # Swoole-based event bus
│           ├── Http/            # HTTP handlers & middleware
│           ├── Persistence/     # SQLite repository
│           └── Template/        # Simple PHP template renderer
├── src/ts/
│   └── main.ts                  # Timeline canvas controller
├── templates/
│   ├── home.php                 # Main page template
│   └── partials/                # Reusable template partials
├── tests/
│   ├── Feature/                 # Feature/integration tests
│   └── Unit/                    # Unit tests
├── swoole-server.php            # Swoole HTTP server entry point
└── phpunit.xml                  # PHPUnit/Pest configuration
```

## Architecture

### CQRS Pattern

Commands (write operations) and queries (read operations) are separated:

```
src/App/Application/
├── Command/
│   ├── CreateItem/
│   │   ├── CreateItemCommand.php
│   │   └── CreateItemHandler.php
│   ├── UpdateItem/
│   ├── DeleteItem/
│   ├── ResizeItem/              # Resize item dates via drag
│   ├── CreateGroup/
│   ├── UpdateGroup/
│   ├── DeleteGroup/
│   └── ReorderGroups/           # Drag & drop group ordering
└── Query/
    ├── GetTimeline/
    │   └── GetTimelineHandler.php
    ├── GetItem/                 # Single item for edit modal
    └── GetGroup/                # Single group for edit modal
```

### Multiplayer with Event Bus

Changes are broadcast to all connected clients via Server-Sent Events:

1. User makes a change (create/update/delete)
2. Command handler emits `TimelineChangedEvent`
3. Event Bus broadcasts to all SSE subscribers
4. Datastar patches the DOM with updated HTML

```php
// Handler emits event
$this->eventBus->emit(new TimelineChangedEvent(
    changeType: 'item_created',
    itemId: $item->id,
));

// SSE endpoint streams updates
$this->eventBus->subscribe(function ($event) use ($response) {
    // Send Datastar-compatible SSE patch
});
```

### Frontend with Datastar

The frontend uses [Datastar](https://data-star.dev/) for declarative reactivity:

```html
<!-- Signals (reactive state) -->
<div data-signals="{_zoom: 1, _panX: 0}">

<!-- SSE connection for real-time updates -->
<div data-init="@get('/updates')">

<!-- Actions with form data -->
<form data-on:submit__prevent="@post('/cmd/items', {contentType: 'form'})">

<!-- Declarative event handlers -->
<div data-on:dragstart="timeline.startDragGroup(evt, 1)"
     data-on:drop="timeline.dropOnTrack(evt, 2)">
```

## API Endpoints

### Pages & SSE

| Method | Path | Description |
|--------|------|-------------|
| GET | `/` | Home page with timeline |
| GET | `/updates` | SSE endpoint for real-time updates |

### Queries (read operations)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/query/timeline` | Get all groups with items (HTML partial) |
| GET | `/query/items/{id}` | Get item for edit modal |
| GET | `/query/groups/{id}` | Get group for edit modal |

### Commands (write operations)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/cmd/items` | Create a new item |
| PUT | `/cmd/items/{id}` | Update an item |
| PATCH | `/cmd/items/{id}/resize` | Resize item dates (drag handles) |
| DELETE | `/cmd/items/{id}` | Delete an item |
| POST | `/cmd/groups` | Create a new group |
| PUT | `/cmd/groups/{id}` | Update a group |
| DELETE | `/cmd/groups/{id}` | Delete a group |
| PUT | `/cmd/groups/reorder` | Reorder groups (drag & drop) |

## Deployment

`deploy/deploy.sh [ref]` deploys a commit from GitHub (default `origin/master`, so push first) to the
checkout on the server, over SSH as a user with passwordless sudo. It stops when the server has
uncommitted changes, copies `data/timeline.db` to `data/backups/` and puts it back after the checkout,
runs `composer install --no-dev`, restarts the `timeline` service and waits until the app answers.
`DEPLOY_HOST`, `DEPLOY_DIR` and `DEPLOY_PORT` override the defaults (`libretto`, `/opt/php-timeline`, 7103).
To roll back, deploy the previous commit; the script prints it.

## Sample Data Categories

The seeder includes example data for:

- 📱 Mobile Phones
- 💻 Computers  
- 🎮 Gaming Consoles
- 🚗 Vehicles
- 🎓 Education
- 💼 Employment
- 🏠 Residences
- ❤️ Relationships
- 🐾 Pets
- ⚽ Hobbies & Sports

## Timeline Controls

| Control | Action |
|---------|--------|
| Ctrl/Cmd + Mouse wheel | Zoom in/out (centered on cursor) |
| Ctrl/Cmd + `+` / `-` / `0` | Zoom in, zoom out, reset the view |
| Click + drag (on timeline), scroll, swipe | Pan the timeline |
| `←` / `→` | Pan |
| `T`, `F` | Go to today, fit the whole timeline |
| `/` | Find an item by title or jump to a year |
| `N`, `G` | New item, new group |
| Drag item edge handles (mouse or finger) | Resize item start/end date |
| Drag group handle (⠿) | Reorder groups |
| Click item | Edit item |
| Click group label | Edit group, move it up or down |

Tab stops once per group. From a group's label, `←` / `→` walk its items and `↑` / `↓` the groups,
`Enter` edits, `Shift` + `←` / `→` moves an item's end, `Alt` + `←` / `→` its start, and
`Alt` + `↑` / `↓` moves the group.

## License

MIT License — see [LICENSE](LICENSE) for details.

---

*Need help taming complexity in your PHP stack?* [zwei und eins gmbh](https://zweiundeins.gmbh) specializes in high-performance web applications.

## Maintainer

Maintained by [Michael Bolli](https://github.com/mbolli) at [zwei und eins gmbh](https://zweiundeins.gmbh), Aarberg, Switzerland.
