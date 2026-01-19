<?php

declare(strict_types=1);

namespace App\Domain\Event;

final class TimelineChangedEvent {
    public function __construct(
        public readonly string $changeType,
        public readonly ?int $itemId = null,
        public readonly ?int $groupId = null,
    ) {}
}
