<?php

declare(strict_types=1);

namespace App\Application\Command\ResizeItem;

final class ResizeItemCommand {
    public function __construct(
        public readonly int $id,
        public readonly string $startDate,
        public readonly ?string $endDate = null,
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
            startDate: $data['startDate'],
            endDate: $endDate,
        );
    }
}
