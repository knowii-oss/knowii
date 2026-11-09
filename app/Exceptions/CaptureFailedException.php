<?php

namespace App\Exceptions;

use Exception;

/**
 * A capture that another try cannot fix (ADR 0018): the address is refused, the site answers "not found". The message
 * is shown to the curator.
 */
class CaptureFailedException extends Exception {}
