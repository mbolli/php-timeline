<?php

declare(strict_types=1);

namespace App\Application\Command\DeleteGroup;

final class DeleteGroupCommand {
    public function __construct(
        public readonly int $id,
    ) {}
}
