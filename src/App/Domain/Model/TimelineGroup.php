<?php

declare(strict_types=1);

namespace App\Domain\Model;

final class TimelineGroup {
    /**
     * @param TimelineItem[] $items
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $icon = '📁',
        public readonly string $color = '#3498db',
        public readonly int $sortOrder = 0,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly array $items = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'],
            icon: $data['icon'] ?? '📁',
            color: $data['color'] ?? '#3498db',
            sortOrder: (int) ($data['sort_order'] ?? 0),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            items: $data['items'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            'sort_order' => $this->sortOrder,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * @param TimelineItem[] $items
     */
    public function withItems(array $items): self {
        return new self(
            id: $this->id,
            name: $this->name,
            icon: $this->icon,
            color: $this->color,
            sortOrder: $this->sortOrder,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            items: $items,
        );
    }
}
