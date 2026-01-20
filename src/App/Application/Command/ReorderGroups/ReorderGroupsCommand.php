<?php

declare(strict_types=1);

namespace App\Application\Command\ReorderGroups;

final readonly class ReorderGroupsCommand {
    /**
     * @param int[] $orderedIds
     */
    public function __construct(
        public array $orderedIds,
    ) {}
}
