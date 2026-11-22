<?php

use App\Exceptions\TechnicalException;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\QueryException;

/*
 * The transaction of an account deletion runs again only for an error that the framework counts as a deadlock or a
 * serialization failure: such an error of the deletion of a community goes out as it is (DeleteCommunity, #1390)
 */

test('a deadlock of the deletion of a community counts as a concurrency error only as it is, not inside a TechnicalException', function () {
    $deadlock = new QueryException('pgsql', 'delete from "communities" where "id" = ?', [1], new PDOException('SQLSTATE[40P01]: Deadlock detected: 7 ERROR:  deadlock detected'));
    $wrapped = new TechnicalException('Failed to delete the community', null, $deadlock);
    $detector = new ConcurrencyErrorDetector;

    expect($detector->causedByConcurrencyError($deadlock))->toBeTrue()
        ->and($detector->causedByConcurrencyError($wrapped))->toBeFalse();
});
