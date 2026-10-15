<?php

namespace App\Http\Controllers\Tenant\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Lets a detached website check that it can reach the tenant API
 */
class PingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => 'ok',
                'version' => 'v1',
            ],
        ]);
    }
}
