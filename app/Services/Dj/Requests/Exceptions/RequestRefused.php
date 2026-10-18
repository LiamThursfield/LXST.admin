<?php

namespace App\Services\Dj\Requests\Exceptions;

use App\Enums\Dj\RequestRefusal;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A song request the gig's settings don't allow, e.g. requests are closed or the queue is full
 */
class RequestRefused extends RuntimeException
{
    public function __construct(public readonly RequestRefusal $reason)
    {
        parent::__construct($reason->message());
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->reason->message(),
            'reason' => $this->reason->value,
        ], $this->reason->httpStatus());
    }
}
