<?php

namespace App\Jobs;

use App\Contracts\Mail\ReceivesMailWebhooks;
use App\Jobs\Middleware\HideMailErrorText;
use App\Support\MailEvent;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * The work of one report of the mail provider (#1478, ADR 0054), on the queue `default`: the webhook answers at once and
 * never waits for it. The report holds an address, so the job is encrypted in the queue.
 */
class HandleMailWebhookEvent implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(public readonly MailEvent $event)
    {
        $this->onQueue('default');
    }

    /**
     * An error keeps no text: the text of a database error can hold the address of the report
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new HideMailErrorText];
    }

    final public function handle(ReceivesMailWebhooks $webhooks): void
    {
        $webhooks->handle($this->event);
    }
}
