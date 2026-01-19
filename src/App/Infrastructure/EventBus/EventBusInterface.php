<?php

declare(strict_types=1);

namespace App\Infrastructure\EventBus;

use App\Domain\Event\TimelineChangedEvent;

interface EventBusInterface {
    /**
     * Subscribe a callback to timeline changes.
     *
     * @param callable(TimelineChangedEvent): void $callback
     *
     * @return string Subscription ID for unsubscribing
     */
    public function subscribe(callable $callback): string;

    /**
     * Unsubscribe from timeline changes.
     */
    public function unsubscribe(string $subscriptionId): void;

    /**
     * Emit an event to all subscribers.
     */
    public function emit(TimelineChangedEvent $event): void;

    /**
     * Get count of active subscribers.
     */
    public function getSubscriberCount(): int;
}
