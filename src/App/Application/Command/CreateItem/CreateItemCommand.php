<?php

declare(strict_types=1);

namespace App\Application\Command\CreateItem;

final class CreateItemCommand {
    /**
     * @param null|array<string, mixed> $metadata
     */
    public function __construct(
        public readonly int $groupId,
        public readonly string $title,
        public readonly string $startDate,
        public readonly ?string $endDate = null,
        public readonly ?string $description = null,
        public readonly ?string $color = null,
        public readonly ?array $metadata = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        return new self(
            groupId: (int) $data['groupId'],
            title: $data['title'],
            startDate: $data['startDate'],
            endDate: $data['endDate'] ?? null,
            description: $data['description'] ?? null,
            color: $data['color'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}
