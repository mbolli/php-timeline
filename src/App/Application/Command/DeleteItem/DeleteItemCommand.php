<?php

declare(strict_types=1);

namespace App\Application\Command\DeleteItem;

final class DeleteItemCommand {
    public function __construct(
        public readonly int $id,
    ) {}
}
