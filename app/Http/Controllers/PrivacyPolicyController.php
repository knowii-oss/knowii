<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class PrivacyPolicyController extends Controller
{
    final public function show(): Response
    {
        $filePath = Jetstream::localizedMarkdownPath('policy.md');

        if (! $filePath) {
            Log::error('Could not load the privacy policy');

            return Inertia::render('PrivacyPolicy', [
                'policy' => '',
            ]);
        }

        $fileContents = file_get_contents($filePath);

        return Inertia::render('PrivacyPolicy', [
            'policy' => $fileContents,
        ]);
    }
}
