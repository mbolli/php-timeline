# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-10-05

### Fixed

- Deleting a group deletes its items: SQLite foreign keys are now on, and items left over from earlier deletes are removed at startup
- The server validates input (title, `YYYY-MM` dates with the end not before the start, `#rrggbb` colors, existing group) and answers 422 with the reason per field; colors can no longer inject CSS
- Deleting an item or a group asks for confirmation, and for a group says how many items go with it
- Dialogs are native `<dialog>` elements: Escape and a click outside close them, focus moves into them, and every label is linked to its field
- Arrow keys no longer pan the timeline while typing or while a dialog is open
- New groups show up in the add-item form without a reload
- Zooming keeps the centre (buttons, keyboard) or the point under the mouse (Ctrl+wheel) in place
- The group labels stay in place while panning; panning uses the native scroll position, so trackpads and scrollbars work too
- Long group names are shortened instead of overflowing into the timeline
- The debug panel only shows outside production; the page has a favicon; the connection status says "Live" or "Offline"

### Changed

- Stylesheet rewritten with cascade layers, nesting, container queries and `color-mix()`; a light theme follows the system setting
- Toolbar buttons use SVG icons with labels; items and group names can be opened with the keyboard

## [0.2.0] - 2026-10-05

### Changed

- Dependencies updated: Mezzio 3.28, mezzio-swoole 4.14 (PHP 8.5 support), datastar-php 1.0.1, Pest 4.7, PHPStan 2.2, TypeScript 7, esbuild 0.28
- Datastar 1.0.4 replaces 1.0.0-RC.7; expressions use `el` and `evt` instead of `this` and `event`
- PHP 8.4 or newer and Swoole 6 are required; `laminas/laminas-cli` is a direct dependency again for `mezzio:swoole:start`
- `rector/type-perfect` removed, `tomasvotruba/type-coverage` now ships it

### Fixed

- The add-item and add-group forms clear after submitting again

## [0.1.0] - 2026-01-23

### Added

- **Horizontal Timeline** — Zoomable month/year grid with pan and zoom controls
- **Vertical Grouping** — Track-based layout for categorizing events by life area
- **Resize Handles** — Drag item edges to adjust start/end dates
- **Drag & Drop Reordering** — Reorder groups via drag handle
- **Real-time Multiplayer** — SSE-based updates via Event Bus pattern
- **CQRS Architecture** — Separated command and query handlers
- **Swoole HTTP Server** — High-performance persistent PHP server
- **Datastar Frontend** — Reactive UI with minimal JavaScript
- **SQLite Database** — Simple, file-based persistence
- **Production Ready** — Caddy config, systemd service, security headers

### Technical Stack

- PHP 8.2+ with Swoole extension
- Mezzio 3.19 (PSR-7/PSR-15 middleware)
- Datastar 1.0 for reactive frontend
- TypeScript 5.7 with esbuild
- Pest 4.0 for testing
- PHPStan for static analysis

[0.1.0]: https://github.com/mbolli/php-timeline/releases/tag/v0.1.0
