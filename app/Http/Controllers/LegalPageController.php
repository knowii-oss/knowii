<?php

namespace App\Http\Controllers;

use App\Support\LegalPages;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every legal page (#904): the route gives the page name, App\Support\LegalPages renders its Markdown file.
 *
 * An instance that has no file for a page has no such page (#1653): the address answers 404. The check runs at each
 * request, so a file that the operator copies later is served without a restart.
 */
class LegalPageController extends Controller
{
    final public function show(string $page): Response
    {
        abort_unless(LegalPages::exists($page), 404);

        return Inertia::render('Legal/Show', LegalPages::render($page));
    }
}
