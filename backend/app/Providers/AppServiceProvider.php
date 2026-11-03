<?php

namespace App\Providers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationItResponsible;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserOrganizationAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
        $this->configureTrustedProxies();
    }

    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'organization' => Organization::class,
            'department' => Department::class,
            'employee' => Employee::class,
            'organization_it_responsible' => OrganizationItResponsible::class,
            'user' => User::class,
            'user_organization_access' => UserOrganizationAccess::class,
            'system_setting' => SystemSetting::class,
            'role' => Role::class,
        ]);
    }

    private function configureTrustedProxies(): void
    {
        $proxies = config('app.trusted_proxies');
        if (is_string($proxies) && $proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('username')).'|'.$request->ip()),
            Limit::perMinute(30)->by('login-ip|'.$request->ip()),
        ]);
    }
}
