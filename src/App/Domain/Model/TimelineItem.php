<?php

declare(strict_types=1);

namespace App\Domain\Model;

final class TimelineItem {
    /**
     * @param null|array<string, mixed> $metadata
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $groupId,
        public readonly string $title,
        public readonly string $startDate,
        public readonly ?string $endDate = null,
        public readonly ?string $description = null,
        public readonly ?string $color = null,
        public readonly ?array $metadata = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        $metadata = $data['metadata'] ?? null;
        if (\is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        return new self(
            id: $data['id'] ?? null,
            groupId: (int) $data['group_id'],
            title: $data['title'],
            startDate: $data['start_date'],
            endDate: $data['end_date'] ?? null,
            description: $data['description'] ?? null,
            color: $data['color'] ?? null,
            metadata: $metadata,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'group_id' => $this->groupId,
            'title' => $this->title,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'description' => $this->description,
            'color' => $this->color,
            'metadata' => $this->metadata ? json_encode($this->metadata) : null,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function isOngoing(): bool {
        return $this->endDate === null;
    }

    public function getDurationMonths(): ?int {
        if ($this->endDate === null) {
            return null;
        }

        $start = new \DateTimeImmutable($this->startDate . '-01');
        $end = new \DateTimeImmutable($this->endDate . '-01');
        $diff = $start->diff($end);

        return ($diff->y * 12) + $diff->m;
    }
}
