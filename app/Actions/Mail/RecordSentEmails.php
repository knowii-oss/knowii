<?php

namespace App\Actions\Mail;

use App\Contracts\Mail\RecordsSentEmails;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Visus\Cuid2\Cuid2;

/**
 * The count of sent mail (#1477, ADR 0054): one statement adds the recipients to the row of the current calendar month
 * (UTC), and creates the row on the first mail of the month. Two workers that send at the same time both count. The cuid
 * of a new row is ignored when the row exists.
 */
final class RecordSentEmails implements RecordsSentEmails
{
    final public function record(int $recipients): void
    {
        if ($recipients <= 0) {
            return;
        }

        $now = CarbonImmutable::now('UTC');

        DB::statement(
            'insert into mail_usage_months (cuid, month, sent_count, created_at, updated_at) values (?, ?, ?, ?, ?) '
            .'on conflict (month) do update set sent_count = mail_usage_months.sent_count + excluded.sent_count, updated_at = excluded.updated_at',
            [(new Cuid2(24))->toString(), $now->startOfMonth()->toDateString(), $recipients, $now, $now],
        );
    }
}
