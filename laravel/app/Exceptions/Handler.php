<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (ConcurrencyConflictException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        });

        $this->renderable(function (InvalidRequestStateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $this->renderable(function (NotFoundHttpException $e) {
            return response()->json(['message' => 'Resource not found.'], 404);
        });

        $this->renderable(function (AuthenticationException $e) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        });

        $this->renderable(function (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        });
    }
}
