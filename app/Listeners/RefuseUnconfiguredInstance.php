<?php

namespace App\Listeners;

use App\Constants;
use App\Exceptions\InstanceNotConfiguredException;
use App\Support\InstanceValues;
use Illuminate\Console\Events\CommandStarting;

/**
 * In production, the long-running processes (the queue worker, the scheduler, the WebSocket server) refuse to start while
 * the instance names no operator or no contact address (#1653, ADR 0055): artisan prints the values to set and exits
 * with 1. Every other command runs, so that an operator can still generate a key, build the caches, migrate and check
 * the health. The listeners of the app are found by their `handle...` methods.
 */
class RefuseUnconfiguredInstance
{
    final public function handleCommandStarting(CommandStarting $event): void
    {
        if (! in_array($event->command, Constants::INSTANCE_CHECKED_COMMANDS, true)) {
            return;
        }

        $missing = InstanceValues::missing();
        if ($missing !== []) {
            throw new InstanceNotConfiguredException(InstanceValues::notConfiguredMessage($missing));
        }
    }
}
