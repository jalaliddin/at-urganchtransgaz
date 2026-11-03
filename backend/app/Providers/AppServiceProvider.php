<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationItResponsible;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserOrganizationAccess;
use App\Support\Access\OrganizationAccessResolver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\Files\FileAccess::class);
        $this->app->scoped(OrganizationAccessResolver::class);
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureAccessCache();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureTrustedProxies();

        ResetPassword::createUrlUsing(fn ($user, string $token): string => rtrim((string) config('app.url'), '/')
            .'/reset-password?token='.urlencode($token).'&email='.urlencode((string) $user->email));
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
            'audit_log' => AuditLog::class,
            'stored_file' => \App\Models\StoredFile::class,
            'ticket_category' => \App\Models\TicketCategory::class,
            'ticket_sla_policy' => \App\Models\TicketSlaPolicy::class,
            'ticket' => \App\Models\Ticket::class,
            'ticket_comment' => \App\Models\TicketComment::class,
            'ticket_attachment' => \App\Models\TicketAttachment::class,
            'document' => \App\Models\Document::class,
            'document_version' => \App\Models\DocumentVersion::class,
            'report_export' => \App\Models\ReportExport::class,
        ]);
    }

    private function configureTrustedProxies(): void
    {
        $proxies = config('app.trusted_proxies');
        if (is_string($proxies) && $proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    }

    private function configureAccessCache(): void
    {
        // Access scopes are memoised per request; never let them leak into the next
        // request handled by the same application instance (tests, Octane).
        Event::listen(RequestHandled::class, fn () => $this->app->make(OrganizationAccessResolver::class)->flush());
    }

    private function configureAuthorization(): void
    {
        // Super Admin passes every ability check; everyone else goes through permissions/policies.
        Gate::before(fn ($user) => $user instanceof User && $user->isSuperAdmin() ? true : null);

        // OpenAPI docs (/docs/api): always open locally, otherwise Super Admin only.
        $files = $this->app->make(\App\Services\Files\FileAccess::class);
        $files->register(fn (User $user, \App\Models\StoredFile $file): ?bool => $this->app
            ->make(\App\Services\Helpdesk\TicketAttachments::class)->canDownload($user, $file));

        Gate::define('helpdesk.use', fn (User $user): bool => $user->canAny(['helpdesk.view', 'helpdesk.create', 'helpdesk.manage']));
        Gate::define('viewApiDocs', fn (?User $user): bool => $user?->isSuperAdmin() ?? false);
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
