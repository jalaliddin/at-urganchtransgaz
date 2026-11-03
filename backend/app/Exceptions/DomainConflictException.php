<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule prevents the requested action (rendered as HTTP 409 "conflict").
 */
class DomainConflictException extends RuntimeException {}
