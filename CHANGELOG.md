# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Dependencies updated: Datastar 1.0.5, datastar-php 1.0.3, Mezzio 3.29, laminas-cli 1.16, laminas-diactoros 3.10, laminas-servicemanager 4.6, PHPStan 2.3, php-cs-fixer 3.95.32, type-coverage 2.5, `@mbolli/datastar-attribute-on-keys` 2.0
- Pest 5 on PHPUnit 13 replaces Pest 4 on PHPUnit 12
- Composer no longer needs `minimum-stability: RC`, since datastar-php is stable
- `composer serve` no longer stops after Composer's 300 second process timeout
- The README lists pnpm instead of npm and the current versions
- Redesigned interface: neutral chrome in light and dark, tinted item bars with readable text, a two-row ruler with month names that adapts to the zoom, a floating dock for zoom, Fit and Today, and dialogs that open as a sheet on phones
- The timeline opens on today and Reset view returns there; the today marker sits in the ruler
- Item titles stay inside the visible part of their bar, and a year that is wider than the screen keeps its label
- Items that follow each other in the same month share a lane
- Dialogs stay open until the server has saved, and show its reason or a connection failure with the input intact
- Deletes are confirmed in the page's own dialog instead of the browser's `confirm()`
- New items take their group's color unless one is chosen
- On a phone the label column shrinks to the icons and each track carries its group's name

### Added

- An edit lock (`L` or the Lock button in the app bar): while it is on, items and groups open read-only and nothing can be resized, dragged, added or saved. Touch screens start locked; the choice is stored in the browser
- Undo for resizes, edits, reorders and new items and groups, from the toast after each change or with Ctrl/Cmd+Z, several steps back; other tabs see the undo like any change
- Creating an item or a group answers 201 with the new id
- Search for an item by title or jump to a year (`/`), and shortcuts for today (`T`), fit (`F`), new item (`N`) and new group (`G`)
- Keyboard access: one tab stop per group, arrow keys through its items, Shift or Alt with an arrow to move an item's end or start, Alt with up or down to move a group
- Resize handles work with a finger; groups can be moved with buttons in their dialog
- Playwright end-to-end tests (`pnpm test:e2e`) with their own server and database
- `TIMELINE_PORT`, `TIMELINE_DB` and `TIMELINE_PID_FILE` override the configuration; `bin/seed.php` takes a database path

### Fixed

- The server starts on OpenSwoole, which has no `swoole_set_process_name()`
- Ctrl/Cmd with `+` and `-` zoom; the key names used before never matched
- A resize or reorder that the server did not save puts the bar or the order back and says so
- A live update no longer changes the group selected in an open New item dialog; if that group was deleted elsewhere, the dialog says so
- New groups go to the end of the list, and editing a group no longer moves it to the top
- Focus returns to the item or group after its dialog closes
- Datastar is requested once instead of through two script entries

## [0.3.1] - 2026-10-05

### Fixed

- Touch screens scroll the timeline again in both directions; the custom touch handlers that blocked vertical scrolling are gone, since panning uses the native scroll position
- No `timeline is not defined` error when Datastar starts before the app script has loaded

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
