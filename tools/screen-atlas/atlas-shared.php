<?php

/*
 * What tools/screen-atlas/atlas-seed.php and atlas-unseed.php share: the seed makes it, the unseed removes it, and both
 * read the same number.
 */

if (! defined('ATLAS_GROWER_COUNT')) {
    // The members who answer the events (#1515): the accounts atlas-grower-01@example.com to atlas-grower-22@example.com.
    // 22, so that one event has 21 Yes answers and its list "Answers" shows "Load more" (20 names at a time)
    define('ATLAS_GROWER_COUNT', 22);
}
