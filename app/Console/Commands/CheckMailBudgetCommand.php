<?php

namespace App\Console\Commands;

use App\Contracts\Mail\ChecksMailBudgets;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The hourly check of the mail budget (#1477, ADR 0054): the alert at 80% of the monthly quota and the hold of the
 * notification mail. It prints one line: the mail of the month, the quota, the percent, where the numbers come from, and
 * whether notification mail is held.
 */
#[Signature('mail:check-budget')]
#[Description('Compare the mail of the month with the quota: alert the operator at 80%, hold notification mail at the hold percent')]
class CheckMailBudgetCommand extends Command
{
    public function handle(ChecksMailBudgets $budget): int
    {
        $result = $budget->check();

        $quota = $result->quota === null ? 'none' : (string) $result->quota;
        $percent = $result->percent === null ? '-' : $result->percent.'%';
        $held = $result->held() && $result->heldUntil !== null ? 'held until '.$result->heldUntil->toIso8601String() : 'not held';

        $this->line("Mail of the month: {$result->used}, quota: {$quota}, used: {$percent}, source: {$result->source}, notification mail: {$held}");

        return self::SUCCESS;
    }
}
