<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserTenantVerification
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = app('currentTenant');

        if (! $user || ! $tenant) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->tenant_id !== $tenant->id) {
            return response()->json([
                'code' => 403,
                'message' => 'Unauthorized',
                'error' => 'Access denied: user does not belong to this tenant.',
            ], 403);
        }

        return $next($request);
    }
}
