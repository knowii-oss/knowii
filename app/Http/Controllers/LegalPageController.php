<?php

namespace App\Http\Controllers;

use App\Support\LegalPages;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every legal page (#904): the route gives the page name, App\Support\LegalPages renders its Markdown file.
 */
class LegalPageController extends Controller
{
    final public function show(string $page): Response
    {
        return Inertia::render('Legal/Show', LegalPages::render($page));
    }
}
