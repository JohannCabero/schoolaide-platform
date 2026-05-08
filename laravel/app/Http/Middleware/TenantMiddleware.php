<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if (! $tenant || ! $tenant->is_active) {
            return response()->json([
                'code' => 404,
                'message' => 'Tenant not found or inactive.',
            ], 404);
        }

        // Bind tenant into container so TenantScope can use it
        app()->instance('currentTenant', $tenant);

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $slug = $request->header('X-Tenant');
        if ($slug) {
            return Tenant::where('slug', $slug)->first();
        }

        $host = $request->getHost();

        $tenant = Tenant::where('domain', $host)->first();
        if ($tenant) {
            return $tenant;
        }

        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            $subdomain = $parts[0];
            return Tenant::where('slug', $subdomain)->first();
        }

        return null;
    }
}
