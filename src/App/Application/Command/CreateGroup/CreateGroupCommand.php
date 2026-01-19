<?php

declare(strict_types=1);

namespace App\Application\Command\CreateGroup;

final class CreateGroupCommand {
    public function __construct(
        public readonly string $name,
        public readonly string $icon = '📁',
        public readonly string $color = '#3498db',
        public readonly int $sortOrder = 0,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        return new self(
            name: $data['name'],
            icon: $data['icon'] ?? '📁',
            color: $data['color'] ?? '#3498db',
            sortOrder: (int) ($data['sortOrder'] ?? 0),
        );
    }
}
