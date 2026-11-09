<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetDisposal;
use App\Models\AssetMaintenance;
use App\Models\AssetModel;
use App\Models\AssetTransfer;
use App\Models\AuditLog;
use App\Models\CartridgeModel;
use App\Models\CartridgeReplacement;
use App\Models\Change;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Employee;
use App\Models\InventorySession;
use App\Models\ItService;
use App\Models\KbArticle;
use App\Models\Location;
use App\Models\MaintenancePlan;
use App\Models\Manufacturer;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Organization;
use App\Models\OrganizationItResponsible;
use App\Models\Problem;
use App\Models\ReportExport;
use App\Models\Requisition;
use App\Models\Role;
use App\Models\ServiceContract;
use App\Models\ServiceInvoice;
use App\Models\ServicePayment;
use App\Models\ServiceProvider as TelecomProvider;
use App\Models\SimCard;
use App\Models\Software;
use App\Models\SoftwareLicense;
use App\Models\SoftwarePolicy;
use App\Models\StockCount;
use App\Models\StockDocument;
use App\Models\StoredFile;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskRecurrence;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketSlaPolicy;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserOrganizationAccess;
use App\Models\Warehouse;
use App\Services\Assets\AssetAttachments;
use App\Services\Assets\AssetTransferService;
use App\Services\Documents\DocumentService;
use App\Services\Files\FileAccess;
use App\Services\Helpdesk\TicketAttachments;
use App\Services\Requisitions\RequisitionService;
use App\Services\Tasks\TaskService;
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
        $this->app->singleton(FileAccess::class);
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
            'stored_file' => StoredFile::class,
            'ticket_category' => TicketCategory::class,
            'ticket_sla_policy' => TicketSlaPolicy::class,
            'ticket' => Ticket::class,
            'ticket_comment' => TicketComment::class,
            'ticket_attachment' => TicketAttachment::class,
            'document' => Document::class,
            'document_version' => DocumentVersion::class,
            'report_export' => ReportExport::class,
            'asset_category' => AssetCategory::class,
            'manufacturer' => Manufacturer::class,
            'asset_model' => AssetModel::class,
            'location' => Location::class,
            'asset' => Asset::class,
            'asset_assignment' => AssetAssignment::class,
            'asset_maintenance' => AssetMaintenance::class,
            'asset_disposal' => AssetDisposal::class,
            'asset_transfer' => AssetTransfer::class,
            'inventory_session' => InventorySession::class,
            'unit' => Unit::class,
            'material_category' => MaterialCategory::class,
            'material' => Material::class,
            'warehouse' => Warehouse::class,
            'stock_document' => StockDocument::class,
            'stock_count' => StockCount::class,
            'requisition' => Requisition::class,
            'software' => Software::class,
            'software_license' => SoftwareLicense::class,
            'software_policy' => SoftwarePolicy::class,
            'service_provider' => TelecomProvider::class,
            'sim_card' => SimCard::class,
            'service_contract' => ServiceContract::class,
            'it_service' => ItService::class,
            'service_invoice' => ServiceInvoice::class,
            'service_payment' => ServicePayment::class,
            'task_board' => TaskBoard::class,
            'task' => Task::class,
            'task_recurrence' => TaskRecurrence::class,
            'cartridge_model' => CartridgeModel::class,
            'cartridge_replacement' => CartridgeReplacement::class,
            'kb_article' => KbArticle::class,
            'problem' => Problem::class,
            'change' => Change::class,
            'maintenance_plan' => MaintenancePlan::class,
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
        $files = $this->app->make(FileAccess::class);
        $files->register(fn (User $user, StoredFile $file): ?bool => $this->app
            ->make(TicketAttachments::class)->canDownload($user, $file));

        $files->register(function (User $user, StoredFile $file): ?bool {
            $owner = ReportExport::query()->where('stored_file_id', $file->id)->value('user_id');

            return $owner === null ? null : (int) $owner === $user->id;
        });

        $files->register(fn (User $user, StoredFile $file): ?bool => $this->app
            ->make(DocumentService::class)->canDownload($user, $file));

        $files->register(fn (User $user, StoredFile $file): ?bool => $this->app
            ->make(AssetAttachments::class)->canDownload($user, $file));

        $files->register(fn (User $user, StoredFile $file): ?bool => $this->app
            ->make(AssetTransferService::class)->canDownload($user, $file));

        $files->register(function (User $user, StoredFile $file): ?bool {
            $documentId = StockDocument::query()->where('file_id', $file->id)->value('id');
            if ($documentId === null) {
                return null;
            }

            return $user->can('warehouse.view') && StockDocument::query()->visibleTo($user)->whereKey($documentId)->exists();
        });

        $files->register(fn (User $user, StoredFile $file): ?bool => $this->app
            ->make(RequisitionService::class)->canDownload($user, $file));

        $files->register(fn (User $user, StoredFile $file): ?bool => $this->app
            ->make(TaskService::class)->canDownload($user, $file));

        // Invoice and payment scans: anyone working with services of the invoice's organization.
        $files->register(function (User $user, StoredFile $file): ?bool {
            $organizationId = ServiceInvoice::query()->withoutGlobalScopes()->where('file_id', $file->id)->value('organization_id')
                ?? ServiceInvoice::query()->withoutGlobalScopes()
                    ->whereIn('id', ServicePayment::query()->select('invoice_id')->where('file_id', $file->id))->value('organization_id');
            if ($organizationId === null) {
                return null;
            }

            return Gate::forUser($user)->allows('services.use') && $user->canAccessOrganization((int) $organizationId);
        });

        Gate::define('assets.use', fn (User $user): bool => $user->canAny(['assets.view', 'assets.create', 'assets.inventory']));
        Gate::define('warehouse.catalog', fn (User $user): bool => $user->canAny(['warehouse.view', 'requisitions.create', 'requisitions.review', 'requisitions.fulfill']));
        Gate::define('software.use', fn (User $user): bool => $user->canAny(['software.view', 'software.install', 'software.manage']));
        Gate::define('sim.use', fn (User $user): bool => $user->canAny(['sim.view', 'sim.assign', 'sim.manage']));
        Gate::define('services.use', fn (User $user): bool => $user->canAny(['services.view', 'services.manage', 'services.invoice', 'services.approve', 'services.pay']));
        Gate::define('telecom.use', fn (User $user): bool => Gate::forUser($user)->any(['sim.use', 'services.use']));
        Gate::define('telecom.providers', fn (User $user): bool => $user->canAny(['sim.manage', 'services.manage']));
        Gate::define('helpdesk.use', fn (User $user): bool => $user->canAny(['helpdesk.view', 'helpdesk.create', 'helpdesk.manage']));
        Gate::define('viewApiDocs', fn (?User $user): bool => $user?->isSuperAdmin() ?? false);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        // Agents: per token (hash of the bearer value, never the value itself).
        RateLimiter::for('agent', fn (Request $request) => Limit::perMinute(30)->by('agent|'.hash('sha256', (string) $request->bearerToken())));
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('username')).'|'.$request->ip()),
            Limit::perMinute(30)->by('login-ip|'.$request->ip()),
        ]);
    }
}
