<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class PrivacyPolicyController extends Controller
{
  final public function show(): Response
  {
    // Fetch the privacy policy content from a file or database
    $policyContent = file_get_contents(Jetstream::localizedMarkdownPath('policy.md'));

    return Inertia::render('PrivacyPolicy', [
      'policy' => $policyContent,
    ]);
  }
}
