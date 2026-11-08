<?php

namespace Tests\Feature\Warehouse;

use App\Enums\StockAlertType;
use App\Enums\StockDocumentType;
use App\Models\Material;
use App\Models\Organization;
use App\Models\StockAlert;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\Warehouse\StockExpiringNotification;
use App\Notifications\Warehouse\StockLowNotification;
use App\Services\Exports\StockBalanceReport;
use App\Services\Exports\StockMovementReport;
use App\Services\Warehouse\StockService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesWarehouse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class WarehouseAlertsReportsTest extends TestCase
{
    use CreatesWarehouse, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private Warehouse $warehouse;

    private User $it;

    private Material $toner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWarehouse();
        $this->org = Organization::factory()->create();
        $this->warehouse = $this->warehouseIn($this->org);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org);
        $this->toner = $this->material(['name' => 'Toner', 'min_quantity' => 5]);
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function postStock(StockDocumentType $type, array $lines, ?Warehouse $warehouse = null): void
    {
        app(StockService::class)->post($type, ['warehouse_id' => ($warehouse ?? $this->warehouse)->id, 'reason' => 'test'], $lines, $this->it);
    }

    public function test_low_stock_alert_opens_once_per_episode_and_resolves(): void
    {
        Notification::fake();
        $this->postStock(StockDocumentType::Receipt, [['material_id' => $this->toner->id, 'quantity' => 8]]);
        Notification::assertNothingSent();

        $this->postStock(StockDocumentType::Issue, [['material_id' => $this->toner->id, 'quantity' => 4]]);
        $this->postStock(StockDocumentType::Issue, [['material_id' => $this->toner->id, 'quantity' => 1]]);
        Notification::assertSentToTimes($this->it, StockLowNotification::class, 1);
        $this->assertSame(1, StockAlert::query()->withoutGlobalScopes()->whereNull('resolved_at')->count());

        $this->postStock(StockDocumentType::Receipt, [['material_id' => $this->toner->id, 'quantity' => 10]]);
        $this->assertSame(0, StockAlert::query()->withoutGlobalScopes()->whereNull('resolved_at')->count());

        $this->postStock(StockDocumentType::Issue, [['material_id' => $this->toner->id, 'quantity' => 12]]);
        Notification::assertSentToTimes($this->it, StockLowNotification::class, 2);
        $this->assertSame(2, StockAlert::query()->withoutGlobalScopes()->where('type', StockAlertType::LowStock)->count());
    }

    public function test_daily_check_raises_expiry_alerts_once_per_lot(): void
    {
        Notification::fake();
        $battery = $this->material(['name' => 'Batareya', 'track_batches' => true, 'track_expiry' => true]);
        $this->postStock(StockDocumentType::Receipt, [
            ['material_id' => $battery->id, 'quantity' => 2, 'batch' => 'SOON', 'expires_at' => now()->addDays(20)->toDateString()],
            ['material_id' => $battery->id, 'quantity' => 2, 'batch' => 'LATER', 'expires_at' => now()->addDays(200)->toDateString()],
        ]);

        $this->artisan('warehouse:stock-check')->assertSuccessful();
        $this->artisan('warehouse:stock-check')->assertSuccessful();

        Notification::assertSentToTimes($this->it, StockExpiringNotification::class, 1);
        $this->assertSame(['SOON'], StockAlert::query()->withoutGlobalScopes()->where('type', StockAlertType::Expiring)->pluck('batch')->all());
    }

    public function test_dashboard_shows_warehouse_widget_in_scope(): void
    {
        $battery = $this->material(['name' => 'Batareya', 'track_batches' => true, 'track_expiry' => true]);
        $this->postStock(StockDocumentType::Receipt, [
            ['material_id' => $this->toner->id, 'quantity' => 2],
            ['material_id' => $battery->id, 'quantity' => 1, 'batch' => 'X', 'expires_at' => now()->addDays(5)->toDateString()],
        ]);
        $this->postStock(StockDocumentType::Receipt, [['material_id' => $this->toner->id, 'quantity' => 1]], $this->warehouseIn(Organization::factory()->create()));

        $this->actingAs($this->it);
        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.warehouse.low_stock', 1)
            ->assertJsonPath('data.warehouse.expiring', 1)
            ->assertJsonPath('data.warehouse.documents_month', 1);

        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->org));
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonMissingPath('data.warehouse');
    }

    public function test_exports_apply_the_owner_scope_without_an_authenticated_user(): void
    {
        $this->postStock(StockDocumentType::Receipt, [['material_id' => $this->toner->id, 'quantity' => 3, 'unit_cost' => 10]]);
        $this->postStock(StockDocumentType::Receipt, [['material_id' => $this->toner->id, 'quantity' => 9]], $this->warehouseIn(Organization::factory()->create()));
        Auth::logout();

        $balances = iterator_to_array(app(StockBalanceReport::class)->rows($this->it, [], 'uz'), false);
        $this->assertCount(1, $balances);
        $this->assertContains('Toner', $balances[0]);
        $this->assertContains(3.0, $balances[0]);

        $movements = iterator_to_array(app(StockMovementReport::class)->rows($this->it, ['type' => 'receipt'], 'uz'), false);
        $this->assertCount(1, $movements);

        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->org));
        $this->postJson('/api/v1/exports', ['type' => 'stock_balances', 'format' => 'xlsx'])->assertForbidden();
        $this->actingAs($this->it);
        $this->postJson('/api/v1/exports', ['type' => 'stock_movements', 'format' => 'xlsx'])->assertStatus(202);
    }
}
