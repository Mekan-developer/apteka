<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PingController extends Controller
{
    /**
     * Confirm that API v1 is reachable, using the standard { data, message } envelope.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'version' => 'v1',
                'time' => now()->toIso8601String(),
            ],
            'message' => __('messages.api_available'),
        ]);
    }
}
