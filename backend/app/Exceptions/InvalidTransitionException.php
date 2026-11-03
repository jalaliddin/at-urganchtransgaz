<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The requested workflow step is not allowed from the record's current state
 * (rendered as HTTP 422 with code "invalid_transition").
 */
class InvalidTransitionException extends RuntimeException {}
