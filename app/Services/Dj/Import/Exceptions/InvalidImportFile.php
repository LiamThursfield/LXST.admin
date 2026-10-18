<?php

namespace App\Services\Dj\Import\Exceptions;

use RuntimeException;

/**
 * The uploaded file can't be imported. The message is shown to the person who uploaded it.
 */
class InvalidImportFile extends RuntimeException {}
