<?php

use App\Http\Controllers\Api\V1\Assets\AssetAssignmentController;
use App\Http\Controllers\Api\V1\Assets\AssetCatalogController;
use App\Http\Controllers\Api\V1\Assets\AssetController;
use App\Http\Controllers\Api\V1\Assets\AssetDetailController;
use App\Http\Controllers\Api\V1\Assets\AssetLifecycleController;
use App\Http\Controllers\Api\V1\Assets\AssetTransferController;
use App\Http\Controllers\Api\V1\Assets\InventorySessionController;
use App\Http\Controllers\Api\V1\Assets\LocationController;
use App\Http\Controllers\Api\V1\Audit\AuditLogController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Departments\DepartmentController;
use App\Http\Controllers\Api\V1\Documents\DocumentController;
use App\Http\Controllers\Api\V1\Documents\DocumentLinkController;
use App\Http\Controllers\Api\V1\Documents\DocumentVersionController;
use App\Http\Controllers\Api\V1\Employees\EmployeeController;
use App\Http\Controllers\Api\V1\Exports\ExportController;
use App\Http\Controllers\Api\V1\Files\FileDownloadController;
use App\Http\Controllers\Api\V1\Health\HealthController;
use App\Http\Controllers\Api\V1\Helpdesk\AssigneeController;
use App\Http\Controllers\Api\V1\Helpdesk\CalendarSettingsController;
use App\Http\Controllers\Api\V1\Helpdesk\SlaPolicyController;
use App\Http\Controllers\Api\V1\Helpdesk\StatsController;
use App\Http\Controllers\Api\V1\Helpdesk\TicketActionController;
use App\Http\Controllers\Api\V1\Helpdesk\TicketAttachmentController;
use App\Http\Controllers\Api\V1\Helpdesk\TicketCategoryController;
use App\Http\Controllers\Api\V1\Helpdesk\TicketCommentController;
use App\Http\Controllers\Api\V1\Helpdesk\TicketController;
use App\Http\Controllers\Api\V1\Helpdesk\TicketDocumentController;
use App\Http\Controllers\Api\V1\Helpdesk\TicketHistoryController;
use App\Http\Controllers\Api\V1\Helpdesk\TicketLinkController;
use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Organizations\ItResponsibleController;
use App\Http\Controllers\Api\V1\Organizations\OrganizationController;
use App\Http\Controllers\Api\V1\Organizations\OrganizationTreeController;
use App\Http\Controllers\Api\V1\Requisitions\RequisitionActionController;
use App\Http\Controllers\Api\V1\Requisitions\RequisitionController;
use App\Http\Controllers\Api\V1\Roles\PermissionController;
use App\Http\Controllers\Api\V1\Roles\RoleController;
use App\Http\Controllers\Api\V1\Search\SearchController;
use App\Http\Controllers\Api\V1\Settings\PublicSettingsController;
use App\Http\Controllers\Api\V1\Settings\SettingsController;
use App\Http\Controllers\Api\V1\Software\AgentController;
use App\Http\Controllers\Api\V1\Software\ComplianceController;
use App\Http\Controllers\Api\V1\Software\ImportController;
use App\Http\Controllers\Api\V1\Software\InstallationController;
use App\Http\Controllers\Api\V1\Software\LicenseController;
use App\Http\Controllers\Api\V1\Software\SoftwareController;
use App\Http\Controllers\Api\V1\Users\UserController;
use App\Http\Controllers\Api\V1\Users\UserOrganizationAccessController;
use App\Http\Controllers\Api\V1\Users\UserPasswordResetController;
use App\Http\Controllers\Api\V1\Users\UserRolesController;
use App\Http\Controllers\Api\V1\Users\UserStatusController;
use App\Http\Controllers\Api\V1\Warehouse\MaterialController;
use App\Http\Controllers\Api\V1\Warehouse\StockBalanceController;
use App\Http\Controllers\Api\V1\Warehouse\StockCountController;
use App\Http\Controllers\Api\V1\Warehouse\StockDocumentController;
use App\Http\Controllers\Api\V1\Warehouse\StockMovementController;
use App\Http\Controllers\Api\V1\Warehouse\WarehouseCatalogController;
use App\Http\Controllers\Api\V1\Warehouse\WarehouseController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

// API v1 routes (prefix api/v1 is set in bootstrap/app.php).

Route::middleware('throttle:public')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');
    Route::get('settings/public', PublicSettingsController::class)->name('settings.public');
});

// Inventory agents authenticate with their own bearer token (not a user session).
Route::post('agent/checkin', [AgentController::class, 'checkin'])->middleware([AuthenticateAgent::class, 'throttle:agent'])->name('agent.checkin');

Route::post('auth/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('auth.login');

Route::middleware('throttle:password-reset')->group(function (): void {
    Route::post('auth/forgot-password', ForgotPasswordController::class)->name('auth.password.forgot');
    Route::post('auth/reset-password', ResetPasswordController::class)->name('auth.password.reset');
});

Route::middleware(['auth:sanctum', 'active', 'password.changed'])->group(function (): void {
    Route::post('auth/logout', [LoginController::class, 'destroy'])->name('auth.logout');
    Route::get('auth/me', [MeController::class, 'show'])->name('auth.me');
    Route::patch('auth/me', [MeController::class, 'update'])->name('auth.me.update');
    Route::put('auth/password', [PasswordController::class, 'update'])->name('auth.password.update');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('files/{uuid}', FileDownloadController::class)->whereUuid('uuid')->name('files.download');
    Route::get('search', SearchController::class)->name('search');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->whereNumber('auditLog')->name('audit-logs.show');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('organizations/tree', OrganizationTreeController::class)->name('organizations.tree');
    Route::apiResource('organizations', OrganizationController::class);
    Route::get('organizations/{organization}/it-responsibles', [ItResponsibleController::class, 'index'])
        ->name('organizations.it-responsibles.index');
    Route::post('organizations/{organization}/it-responsibles', [ItResponsibleController::class, 'store'])
        ->name('organizations.it-responsibles.store');
    Route::delete('organizations/{organization}/it-responsibles/{employee}', [ItResponsibleController::class, 'destroy'])
        ->whereNumber('employee')->name('organizations.it-responsibles.destroy');

    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('employees', EmployeeController::class);

    Route::apiResource('users', UserController::class)->except(['destroy']);
    Route::post('users/{user}/deactivate', [UserStatusController::class, 'deactivate'])->name('users.deactivate');
    Route::post('users/{user}/activate', [UserStatusController::class, 'activate'])->name('users.activate');
    Route::post('users/{user}/reset-password', UserPasswordResetController::class)->name('users.reset-password');
    Route::put('users/{user}/roles', UserRolesController::class)->name('users.roles.update');
    Route::put('users/{user}/organization-access', UserOrganizationAccessController::class)->name('users.access.update');

    Route::get('permissions', PermissionController::class)->name('permissions.index');
    Route::apiResource('roles', RoleController::class);

    Route::apiResource('ticket-categories', TicketCategoryController::class)->except(['show']);
    Route::apiResource('ticket-sla-policies', SlaPolicyController::class)->except(['show']);
    Route::prefix('helpdesk')->name('helpdesk.')->group(function (): void {
        Route::apiResource('tickets', TicketController::class)->except(['destroy']);
        Route::get('assignees', AssigneeController::class)->name('assignees');
        Route::get('stats', StatsController::class)->name('stats');
        Route::post('tickets/{ticket}/transition', [TicketActionController::class, 'transition'])->name('tickets.transition');
        Route::post('tickets/{ticket}/assign', [TicketActionController::class, 'assign'])->name('tickets.assign');
        Route::post('tickets/{ticket}/take', [TicketActionController::class, 'take'])->name('tickets.take');
        Route::post('tickets/{ticket}/rate', [TicketActionController::class, 'rate'])->name('tickets.rate');
        Route::post('tickets/{ticket}/time', [TicketActionController::class, 'time'])->name('tickets.time');
        Route::get('tickets/{ticket}/comments', [TicketCommentController::class, 'index'])->name('tickets.comments.index');
        Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->name('tickets.comments.store');
        Route::get('tickets/{ticket}/history', TicketHistoryController::class)->name('tickets.history');
        Route::get('tickets/{ticket}/attachments', [TicketAttachmentController::class, 'index'])->name('tickets.attachments.index');
        Route::post('tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store'])->name('tickets.attachments.store');
        Route::delete('tickets/{ticket}/attachments/{attachment}', [TicketAttachmentController::class, 'destroy'])
            ->whereNumber('attachment')->name('tickets.attachments.destroy');
        Route::get('tickets/{ticket}/documents', TicketDocumentController::class)->name('tickets.documents');
        Route::get('tickets/{ticket}/links', [TicketLinkController::class, 'index'])->name('tickets.links.index');
        Route::post('tickets/{ticket}/links', [TicketLinkController::class, 'store'])->name('tickets.links.store');
        Route::delete('tickets/{ticket}/links/{link}', [TicketLinkController::class, 'destroy'])
            ->whereNumber('link')->name('tickets.links.destroy');
    });
    Route::get('helpdesk/calendar', [CalendarSettingsController::class, 'show'])->name('helpdesk.calendar.show');
    Route::put('helpdesk/calendar', [CalendarSettingsController::class, 'update'])->name('helpdesk.calendar.update');

    Route::apiResource('documents', DocumentController::class);
    Route::post('documents/{document}/versions', [DocumentVersionController::class, 'store'])->name('documents.versions.store');
    Route::post('documents/{document}/links', [DocumentLinkController::class, 'store'])->name('documents.links.store');
    Route::delete('documents/{document}/links/{link}', [DocumentLinkController::class, 'destroy'])
        ->whereNumber('link')->name('documents.links.destroy');

    Route::get('asset-categories', [AssetCatalogController::class, 'categories'])->name('asset-categories.index');
    Route::post('asset-categories', [AssetCatalogController::class, 'storeCategory'])->name('asset-categories.store');
    Route::put('asset-categories/{assetCategory}', [AssetCatalogController::class, 'updateCategory'])->name('asset-categories.update');
    Route::delete('asset-categories/{assetCategory}', [AssetCatalogController::class, 'destroyCategory'])->name('asset-categories.destroy');
    Route::get('manufacturers', [AssetCatalogController::class, 'manufacturers'])->name('manufacturers.index');
    Route::post('manufacturers', [AssetCatalogController::class, 'storeManufacturer'])->name('manufacturers.store');
    Route::put('manufacturers/{manufacturer}', [AssetCatalogController::class, 'updateManufacturer'])->name('manufacturers.update');
    Route::delete('manufacturers/{manufacturer}', [AssetCatalogController::class, 'destroyManufacturer'])->name('manufacturers.destroy');
    Route::get('asset-models', [AssetCatalogController::class, 'models'])->name('asset-models.index');
    Route::post('asset-models', [AssetCatalogController::class, 'storeModel'])->name('asset-models.store');
    Route::put('asset-models/{assetModel}', [AssetCatalogController::class, 'updateModel'])->name('asset-models.update');
    Route::delete('asset-models/{assetModel}', [AssetCatalogController::class, 'destroyModel'])->name('asset-models.destroy');
    Route::apiResource('locations', LocationController::class)->except(['show']);

    // Phase 4: warehouse catalog
    Route::get('units', [WarehouseCatalogController::class, 'units'])->name('units.index');
    Route::post('units', [WarehouseCatalogController::class, 'storeUnit'])->name('units.store');
    Route::put('units/{unit}', [WarehouseCatalogController::class, 'updateUnit'])->name('units.update');
    Route::delete('units/{unit}', [WarehouseCatalogController::class, 'destroyUnit'])->name('units.destroy');
    Route::get('material-categories', [WarehouseCatalogController::class, 'categories'])->name('material-categories.index');
    Route::post('material-categories', [WarehouseCatalogController::class, 'storeCategory'])->name('material-categories.store');
    Route::put('material-categories/{materialCategory}', [WarehouseCatalogController::class, 'updateCategory'])->name('material-categories.update');
    Route::delete('material-categories/{materialCategory}', [WarehouseCatalogController::class, 'destroyCategory'])->name('material-categories.destroy');
    Route::apiResource('materials', MaterialController::class);
    Route::apiResource('warehouses', WarehouseController::class)->except(['show']);
    Route::get('stock-documents', [StockDocumentController::class, 'index'])->name('stock-documents.index');
    Route::post('stock-documents', [StockDocumentController::class, 'store'])->name('stock-documents.store');
    Route::get('stock-documents/{stockDocument}', [StockDocumentController::class, 'show'])->whereNumber('stockDocument')->name('stock-documents.show');
    Route::post('stock-documents/{stockDocument}/file', [StockDocumentController::class, 'attachFile'])->whereNumber('stockDocument')->name('stock-documents.file');
    Route::get('stock-balances', [StockBalanceController::class, 'index'])->name('stock-balances.index');
    Route::get('stock-availability', [StockBalanceController::class, 'availability'])->name('stock-availability');
    Route::get('stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');
    Route::get('stock-counts', [StockCountController::class, 'index'])->name('stock-counts.index');
    Route::post('stock-counts', [StockCountController::class, 'store'])->name('stock-counts.store');
    Route::get('stock-counts/{stockCount}', [StockCountController::class, 'show'])->whereNumber('stockCount')->name('stock-counts.show');
    Route::patch('stock-counts/{stockCount}/lines', [StockCountController::class, 'record'])->whereNumber('stockCount')->name('stock-counts.record');
    Route::post('stock-counts/{stockCount}/lines', [StockCountController::class, 'addLine'])->whereNumber('stockCount')->name('stock-counts.add-line');
    Route::post('stock-counts/{stockCount}/close', [StockCountController::class, 'close'])->whereNumber('stockCount')->name('stock-counts.close');
    Route::post('stock-counts/{stockCount}/cancel', [StockCountController::class, 'cancel'])->whereNumber('stockCount')->name('stock-counts.cancel');

    // Phase 5: software, licences, antivirus, agents
    Route::get('software-unrecognized', [SoftwareController::class, 'unrecognized'])->name('software.unrecognized');
    Route::apiResource('software', SoftwareController::class)->parameters(['software' => 'software'])->whereNumber('software');
    Route::post('software/{software}/adopt', [SoftwareController::class, 'adopt'])->whereNumber('software')->name('software.adopt');
    Route::apiResource('software-licenses', LicenseController::class)->parameters(['software-licenses' => 'softwareLicense'])->whereNumber('softwareLicense');
    Route::post('software-licenses/{softwareLicense}/reveal', [LicenseController::class, 'reveal'])->whereNumber('softwareLicense')->name('software-licenses.reveal');
    Route::put('assets/{asset}/antivirus', [ComplianceController::class, 'antivirus'])->whereNumber('asset')->name('assets.antivirus');
    Route::get('software-policies', [ComplianceController::class, 'policies'])->name('software-policies.index');
    Route::post('software-policies', [ComplianceController::class, 'storePolicy'])->name('software-policies.store');
    Route::put('software-policies/{softwarePolicy}', [ComplianceController::class, 'updatePolicy'])->whereNumber('softwarePolicy')->name('software-policies.update');
    Route::delete('software-policies/{softwarePolicy}', [ComplianceController::class, 'destroyPolicy'])->whereNumber('softwarePolicy')->name('software-policies.destroy');
    Route::get('software-compliance', [ComplianceController::class, 'summary'])->name('software-compliance');
    Route::get('software-compliance/devices', [ComplianceController::class, 'devices'])->name('software-compliance.devices');
    Route::post('software-imports', ImportController::class)->name('software-imports');
    Route::get('device-agents', [AgentController::class, 'index'])->name('device-agents.index');
    Route::post('assets/{asset}/agent', [AgentController::class, 'store'])->whereNumber('asset')->name('assets.agent');
    Route::post('device-agents/{deviceAgent}/tokens', [AgentController::class, 'token'])->whereNumber('deviceAgent')->name('device-agents.token');
    Route::post('device-agents/{deviceAgent}/revoke', [AgentController::class, 'revoke'])->whereNumber('deviceAgent')->name('device-agents.revoke');
    Route::get('device-agents/{deviceAgent}/checkins', [AgentController::class, 'checkins'])->whereNumber('deviceAgent')->name('device-agents.checkins');
    Route::get('assets/{asset}/software', [InstallationController::class, 'index'])->whereNumber('asset')->name('assets.software');
    Route::post('assets/{asset}/software', [InstallationController::class, 'store'])->whereNumber('asset')->name('assets.software.store');
    Route::patch('software-installations/{softwareInstallation}', [InstallationController::class, 'update'])->whereNumber('softwareInstallation')->name('software-installations.update');
    Route::delete('software-installations/{softwareInstallation}', [InstallationController::class, 'destroy'])->whereNumber('softwareInstallation')->name('software-installations.destroy');

    // Phase 4: requisitions
    Route::apiResource('requisitions', RequisitionController::class)->whereNumber('requisition');
    Route::prefix('requisitions/{requisition}')->whereNumber('requisition')->name('requisitions.')->group(function (): void {
        Route::post('submit', [RequisitionActionController::class, 'submit'])->name('submit');
        Route::post('org-decision', [RequisitionActionController::class, 'orgDecision'])->name('org-decision');
        Route::post('take', [RequisitionActionController::class, 'take'])->name('take');
        Route::post('request-info', [RequisitionActionController::class, 'requestInfo'])->name('request-info');
        Route::post('provide-info', [RequisitionActionController::class, 'provideInfo'])->name('provide-info');
        Route::post('decision', [RequisitionActionController::class, 'decide'])->name('decision');
        Route::post('ready', [RequisitionActionController::class, 'ready'])->name('ready');
        Route::post('allocations', [RequisitionActionController::class, 'allocate'])->name('allocations');
        Route::post('complete', [RequisitionActionController::class, 'complete'])->name('complete');
        Route::post('receive', [RequisitionActionController::class, 'receive'])->name('receive');
        Route::post('close', [RequisitionActionController::class, 'close'])->name('close');
        Route::post('comments', [RequisitionActionController::class, 'comment'])->name('comments');
        Route::post('attachments', [RequisitionActionController::class, 'attach'])->name('attachments');
    });

    Route::get('asset-transfers', [AssetTransferController::class, 'index'])->name('asset-transfers.index');
    Route::post('asset-transfers', [AssetTransferController::class, 'store'])->name('asset-transfers.store');
    Route::get('asset-transfers/destinations', [AssetTransferController::class, 'destinations'])->name('asset-transfers.destinations');
    Route::get('asset-transfers/{assetTransfer}', [AssetTransferController::class, 'show'])->whereNumber('assetTransfer')->name('asset-transfers.show');
    Route::post('asset-transfers/{assetTransfer}/items', [AssetTransferController::class, 'addItems'])->name('asset-transfers.items.store');
    Route::delete('asset-transfers/{assetTransfer}/items/{asset}', [AssetTransferController::class, 'removeItem'])
        ->whereNumber('asset')->name('asset-transfers.items.destroy');
    Route::post('asset-transfers/{assetTransfer}/send', [AssetTransferController::class, 'send'])->name('asset-transfers.send');
    Route::post('asset-transfers/{assetTransfer}/receive', [AssetTransferController::class, 'receive'])->name('asset-transfers.receive');
    Route::post('asset-transfers/{assetTransfer}/cancel', [AssetTransferController::class, 'cancel'])->name('asset-transfers.cancel');

    Route::get('asset-disposals', [AssetLifecycleController::class, 'disposals'])->name('asset-disposals.index');
    Route::post('asset-disposals/{assetDisposal}/approve', [AssetLifecycleController::class, 'approve'])->name('asset-disposals.approve');
    Route::post('asset-disposals/{assetDisposal}/reject', [AssetLifecycleController::class, 'reject'])->name('asset-disposals.reject');
    Route::get('assets/{asset}/maintenances', [AssetLifecycleController::class, 'maintenances'])->whereNumber('asset')->name('assets.maintenances.index');
    Route::post('assets/{asset}/maintenances', [AssetLifecycleController::class, 'openMaintenance'])->whereNumber('asset')->name('assets.maintenances.store');
    Route::post('assets/{asset}/maintenances/{maintenance}/close', [AssetLifecycleController::class, 'closeMaintenance'])
        ->whereNumber(['asset', 'maintenance'])->name('assets.maintenances.close');
    Route::post('assets/{asset}/disposals', [AssetLifecycleController::class, 'requestDisposal'])->whereNumber('asset')->name('assets.disposals.store');

    Route::get('inventory-sessions', [InventorySessionController::class, 'index'])->name('inventory-sessions.index');
    Route::post('inventory-sessions', [InventorySessionController::class, 'store'])->name('inventory-sessions.store');
    Route::get('inventory-sessions/{inventorySession}', [InventorySessionController::class, 'show'])->name('inventory-sessions.show');
    Route::get('inventory-sessions/{inventorySession}/items', [InventorySessionController::class, 'items'])->name('inventory-sessions.items');
    Route::post('inventory-sessions/{inventorySession}/scan', [InventorySessionController::class, 'scan'])
        ->middleware('throttle:120,1')->name('inventory-sessions.scan');
    Route::post('inventory-sessions/{inventorySession}/close', [InventorySessionController::class, 'close'])->name('inventory-sessions.close');

    Route::get('assets/mine', [AssetAssignmentController::class, 'mine'])->name('assets.mine');
    Route::post('assets/{asset}/assign', [AssetAssignmentController::class, 'assign'])->whereNumber('asset')->name('assets.assign');
    Route::post('assets/{asset}/return', [AssetAssignmentController::class, 'return'])->whereNumber('asset')->name('assets.return');
    Route::get('assets/{asset}/assignments', [AssetAssignmentController::class, 'history'])->whereNumber('asset')->name('assets.assignments');
    Route::get('employees/{employee}/assets', [AssetAssignmentController::class, 'employeeAssets'])->name('employees.assets');
    Route::get('assets/stats', [AssetController::class, 'stats'])->name('assets.stats');
    Route::get('assets/{asset}/documents', [AssetController::class, 'documents'])->whereNumber('asset')->name('assets.documents');
    Route::get('assets/lookup/{inventory}', [AssetController::class, 'lookup'])->where('inventory', '.+')->name('assets.lookup');
    Route::apiResource('assets', AssetController::class)->whereNumber('asset');
    Route::post('assets/{asset}/status', [AssetController::class, 'status'])->whereNumber('asset')->name('assets.status');
    Route::get('assets/{asset}/events', [AssetDetailController::class, 'events'])->whereNumber('asset')->name('assets.events');
    Route::get('assets/{asset}/attachments', [AssetDetailController::class, 'attachments'])->whereNumber('asset')->name('assets.attachments.index');
    Route::post('assets/{asset}/attachments', [AssetDetailController::class, 'storeAttachments'])->whereNumber('asset')->name('assets.attachments.store');
    Route::delete('assets/{asset}/attachments/{attachment}', [AssetDetailController::class, 'destroyAttachment'])
        ->whereNumber(['asset', 'attachment'])->name('assets.attachments.destroy');
    Route::get('assets/{asset}/relations', [AssetDetailController::class, 'relations'])->whereNumber('asset')->name('assets.relations.index');
    Route::post('assets/{asset}/relations', [AssetDetailController::class, 'storeRelation'])->whereNumber('asset')->name('assets.relations.store');
    Route::delete('assets/{asset}/relations/{relation}', [AssetDetailController::class, 'destroyRelation'])
        ->whereNumber(['asset', 'relation'])->name('assets.relations.destroy');

    Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
    Route::post('exports', [ExportController::class, 'store'])->middleware('throttle:exports')->name('exports.store');
    Route::get('exports/{uuid}', [ExportController::class, 'show'])->whereUuid('uuid')->name('exports.show');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->whereUuid('notification')->name('notifications.read');
});
