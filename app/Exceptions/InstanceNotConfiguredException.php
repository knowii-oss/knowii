<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A long-running process was started in production while a required value of the instance is missing (#1653, ADR 0055)
 */
final class InstanceNotConfiguredException extends RuntimeException {}
