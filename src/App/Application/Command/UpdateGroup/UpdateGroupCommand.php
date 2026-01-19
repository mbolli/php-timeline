<?php

declare(strict_types=1);

namespace App\Application\Command\UpdateGroup;

final class UpdateGroupCommand {
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $icon = '📁',
        public readonly string $color = '#3498db',
        public readonly int $sortOrder = 0,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(int $id, array $data): self {
        return new self(
            id: $id,
            name: $data['name'],
            icon: $data['icon'] ?? '📁',
            color: $data['color'] ?? '#3498db',
            sortOrder: (int) ($data['sortOrder'] ?? 0),
        );
    }
}
