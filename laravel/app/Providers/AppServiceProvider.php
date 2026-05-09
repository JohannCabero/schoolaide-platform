<?php

namespace App\Providers;

use App\Models\ImportLog;
use App\Models\ServiceRequest;
use App\Models\Student;
use App\Models\User;
use App\Policies\ImportLogPolicy;
use App\Policies\ServiceRequestPolicy;
use App\Policies\StudentPolicy;
use App\Policies\UserPolicy;
use App\Services\Api\AuditService;
use App\Services\Api\ImportLogService;
use App\Services\Api\ServiceRequestService;
use App\Services\Api\StudentService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind a null tenant by default — TenantMiddleware overwrites this per-request
        $this->app->instance('currentTenant', null);

        $this->app->singleton(AuditService::class);
        $this->app->singleton(ImportLogService::class);
        $this->app->singleton(StudentService::class);
        $this->app->singleton(ServiceRequestService::class);
    }

    public function boot(): void
    {
        Gate::policy(ServiceRequest::class, ServiceRequestPolicy::class);
        Gate::policy(Student::class,        StudentPolicy::class);
        Gate::policy(User::class,           UserPolicy::class);
        Gate::policy(ImportLog::class,      ImportLogPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        \Laravel\Passport\Passport::tokensExpireIn(now()->addDays(15));
        \Laravel\Passport\Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}
