<?php

namespace App\Services\Exports;

use RuntimeException;

/** The export exceeds what the chosen format can produce; the message is user-facing. */
class ExportTooLargeException extends RuntimeException {}
