<?php

namespace App\Console\Commands;

use App\Contracts\Content\RendersContentAgain;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Renders the stored HTML of posts, comments, messages, pages and quizzes again from their Markdown (#1207, ADR 0040).
 * Run it after `uploads:build-sizes`, so that the HTML gets the `srcset` of the new sizes.
 */
#[Signature('content:render-again {--dry-run : Count the rows whose HTML would change without writing anything}')]
#[Description('Render the stored HTML of member content again from its Markdown')]
class ContentRenderAgainCommand extends Command
{
    public function handle(RendersContentAgain $renderer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = $renderer->renderAgain($dryRun);

        $rows = [];
        foreach ($counts as $kind => $count) {
            $rows[] = [$kind, $count['checked'], $count['changed']];
        }
        $this->table(['Content', 'Read', $dryRun ? 'Would change' : 'Changed'], $rows);

        return self::SUCCESS;
    }
}
