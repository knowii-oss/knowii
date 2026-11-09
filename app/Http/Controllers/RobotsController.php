<?php

namespace App\Http\Controllers;

use App\Support\Robots;
use Illuminate\Http\Response;

/**
 * robots.txt, built from config/robots.php (#104), so that acceptance and self-hosted instances differ.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        return response(Robots::txt(), 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
