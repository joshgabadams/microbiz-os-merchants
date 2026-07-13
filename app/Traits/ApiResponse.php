<?php

namespace App\Traits;

trait ApiResponse
{
    /**
     * Success Response
     */
    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200
    ) {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Error Response
     */
    protected function error(
        string $message = 'An error occurred.',
        int $status = 422,
        mixed $errors = null
    ) {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}