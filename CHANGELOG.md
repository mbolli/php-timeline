# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
