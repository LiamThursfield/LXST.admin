<?php

namespace App\Services\Navigation\Exceptions;

use Exception;

/**
 * Not typically thrown, but all Exceptions in the NavigationService
 * extend this class, allowing for catch-all if necessary
 */
class NavigationServiceException extends Exception {}
