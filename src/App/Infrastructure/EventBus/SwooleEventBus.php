<?php

declare(strict_types=1);

namespace App\Infrastructure\EventBus;

use App\Domain\Event\TimelineChangedEvent;

/**
 * In-memory event bus for Swoole environment.
 * Since Swoole keeps the application alive, this works across requests.
 */
final class SwooleEventBus implements EventBusInterface {
    /** @var array<string, callable> */
    private array $subscribers = [];

    public function subscribe(callable $callback): string {
        $id = uniqid('sub_', true);
        $this->subscribers[$id] = $callback;

        return $id;
    }

    public function unsubscribe(string $subscriptionId): void {
        unset($this->subscribers[$subscriptionId]);
    }

    public function emit(TimelineChangedEvent $event): void {
        foreach ($this->subscribers as $id => $callback) {
            try {
                $callback($event);
            } catch (\Throwable $e) {
                // If callback fails (e.g., connection closed), remove it
                $this->unsubscribe($id);
            }
        }
    }

    public function getSubscriberCount(): int {
        return \count($this->subscribers);
    }
}
