<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class TermsOfServiceController extends Controller
{
    final public function show(): Response
    {
        $filePath = Jetstream::localizedMarkdownPath('terms.md');

        if (! $filePath) {
            Log::error('Could not load the terms of service');

            return Inertia::render('TermsOfService', [
                'terms' => '',
            ]);
        }

        $fileContents = file_get_contents($filePath);

        return Inertia::render('TermsOfService', [
            'terms' => $fileContents,
        ]);
    }
}
