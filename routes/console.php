<?php

use Illuminate\Support\Facades\Schedule;

// The uploaded images that no post or comment uses for a day (#1201)
Schedule::command('uploads:prune')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
