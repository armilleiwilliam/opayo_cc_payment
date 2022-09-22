<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/*
|--------------------------------------------------------------------------
| Api Response Trait
|--------------------------------------------------------------------------
|
| This trait will be used for any response we sent to clients.
|
*/

trait ApiResponse
{
    /**
     * Return a success JSON response.
     *
     * @param string $message
     * @param array $data
     * @param int $code
     *
     * @return JsonResponse
     */
    protected function success(string $message = 'Success', $data = [], int $code = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Return an error JSON response.
     *
     * @param string $message
     * @param array $data
     * @param int $code
     *
     * @return JsonResponse
     */
    protected function error(string $message = 'Internal Server Error', $data = [], int $code = 500): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data
        ], $code);
    }
}
