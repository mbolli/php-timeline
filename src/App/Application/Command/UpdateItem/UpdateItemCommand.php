<?php

declare(strict_types=1);

namespace App\Application\Command\UpdateItem;

final class UpdateItemCommand {
    /**
     * @param null|array<string, mixed> $metadata
     */
    public function __construct(
        public readonly int $id,
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
    public static function fromArray(int $id, array $data): self {
        $endDate = $data['endDate'] ?? null;
        if ($endDate === '') {
            $endDate = null;
        }

        return new self(
            id: $id,
            groupId: (int) $data['groupId'],
            title: $data['title'],
            startDate: $data['startDate'],
            endDate: $endDate,
            description: $data['description'] ?? null,
            color: $data['color'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}
