<?php

namespace Tests\Feature\Telecom;

use App\Enums\SimStatus;
use App\Models\Organization;
use App\Models\ServiceContract;
use App\Models\ServiceInvoice;
use App\Models\User;
use App\Notifications\Telecom\ContractExpiringNotification;
use App\Notifications\Telecom\ServicePaymentNotification;
use App\Services\Exports\ServiceCostReport;
use App\Services\Exports\ServiceInvoiceReport;
use App\Services\Exports\SimCardReport;
use App\Services\Telecom\BillingService;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTelecom;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class TelecomReportsTest extends TestCase
{
    use CreatesTelecom, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private Organization $other;

    private User $head;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->travelTo(CarbonImmutable::parse('2026-11-08 08:15', 'Asia/Tashkent'));
        $this->center = Organization::factory()->create(['name' => 'Urganchtransgaz']);
        $this->branch = Organization::factory()->create(['name' => 'Xiva GTS', 'parent_id' => $this->center->id]);
        $this->other = Organization::factory()->create(['name' => 'Begona']);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->accountant = $this->makeUser(Roles::ACCOUNTANT, $this->branch);
    }

    public function test_daily_billing_generates_periods_and_reminds_once(): void
    {
        Notification::fake();
        $soon = $this->serviceIn($this->branch, ['started_at' => '2026-01-01', 'due_day' => 10]);
        $late = $this->serviceIn($this->branch, ['name' => 'Xosting', 'billing_cycle' => 'quarterly', 'started_at' => '2025-01-01']);
        $this->serviceIn($this->other, ['started_at' => '2026-01-01']);
        ServiceContract::query()->create(['organization_id' => $this->branch->id, 'provider_id' => $this->provider()->id, 'number' => '45/2026', 'valid_to' => '2026-11-30']);
        ServiceContract::query()->create(['organization_id' => $this->branch->id, 'provider_id' => $this->provider()->id, 'number' => '46/2026', 'valid_to' => '2027-06-30']);
        $outsider = $this->makeUser(Roles::ACCOUNTANT, $this->other);

        $this->artisan('services:billing')->assertSuccessful();
        $this->artisan('services:billing')->assertSuccessful();

        $this->assertSame(6, ServiceInvoice::query()->withoutGlobalScopes()->count());
        Notification::assertSentToTimes($this->accountant, ServicePaymentNotification::class, 2);
        Notification::assertSentTo($this->accountant, ServicePaymentNotification::class, fn ($n) => $n->event === 'due_soon' && $n->serviceName === $soon->name);
        Notification::assertSentTo($this->head, ServicePaymentNotification::class, fn ($n) => $n->event === 'overdue' && $n->serviceName === $late->name);
        Notification::assertNotSentTo($this->head, ServicePaymentNotification::class, fn ($n) => $n->event === 'due_soon');
        Notification::assertSentToTimes($this->head, ContractExpiringNotification::class, 1);
        Notification::assertNotSentTo($outsider, ServicePaymentNotification::class, fn ($n) => $n->organization !== 'Begona');
    }

    public function test_dashboard_shows_sim_and_service_widgets_in_scope(): void
    {
        $this->simIn($this->branch, ['monthly_fee' => '45000']);
        $this->simIn($this->branch, ['status' => SimStatus::Active, 'monthly_fee' => '55000', 'provider_id' => $this->operator('Ucell')->id]);
        $this->simIn($this->branch, ['status' => SimStatus::Cancelled]);
        $this->simIn($this->other);
        $service = $this->serviceIn($this->branch, ['started_at' => '2026-01-01']);
        app(BillingService::class)->generate(CarbonImmutable::today());
        ServiceInvoice::query()->where('service_id', $service->id)->where('period_start', '2026-11-01')->update(['amount' => '1500000', 'status' => 'due']);

        $this->actingAs($this->accountant);
        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.sim.total', 2)
            ->assertJsonPath('data.sim.by_status.active', 1)
            ->assertJsonPath('data.sim.by_status.in_stock', 1)
            ->assertJsonPath('data.sim.monthly_fee', '100000.00')
            ->assertJsonCount(2, 'data.sim.by_operator')
            ->assertJsonPath('data.services.month_planned', '1500000.00')
            ->assertJsonPath('data.services.due_soon', 1)
            ->assertJsonPath('data.services.awaiting_approval', 1)
            ->assertJsonPath('data.services.overdue', 0);
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->branch));
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonMissingPath('data.sim')->assertJsonMissingPath('data.services');
    }

    public function test_sim_report_and_cost_analysis_count_per_group_within_scope(): void
    {
        $beeline = $this->operator('Beeline');
        $this->simIn($this->branch, ['monthly_fee' => '45000']);
        $this->simIn($this->branch, ['status' => SimStatus::Active, 'monthly_fee' => '55000']);
        $this->simIn($this->center, ['status' => SimStatus::Blocked, 'provider_id' => $this->operator('Uzmobile')->id]);
        $this->simIn($this->other);

        $internet = $this->serviceIn($this->branch, ['started_at' => '2026-01-01']);
        $phone = $this->serviceIn($this->center, ['name' => 'Telefon', 'type' => 'telephony', 'fee' => '50000', 'started_at' => '2026-01-01']);
        app(BillingService::class)->generate(CarbonImmutable::today());
        ServiceInvoice::query()->where('service_id', $internet->id)->where('period_start', '2026-11-01')->update(['amount' => '1520000', 'paid_amount' => '1520000']);

        $this->actingAs($this->head);
        $rows = collect($this->getJson('/api/v1/sim-report')->assertOk()->json('data.rows'));
        $this->assertCount(2, $rows);
        $branchBeeline = $rows->first(fn ($r) => $r['organization']['id'] === $this->branch->id && $r['provider']['id'] === $beeline->id);
        $this->assertSame(['total' => 2, 'in_stock' => 1, 'active' => 1, 'blocked' => 0, 'returned' => 0, 'monthly_fee' => '100000.00'],
            array_intersect_key($branchBeeline, array_flip(['total', 'in_stock', 'active', 'blocked', 'returned', 'monthly_fee'])));

        $costs = $this->getJson('/api/v1/service-costs?year=2026&group_by=organization')->assertOk()->json('data');
        $branchRow = collect($costs['rows'])->firstWhere('key.id', $this->branch->id);
        $this->assertSame(['planned' => '1500000.00', 'invoiced' => '1520000.00', 'paid' => '1520000.00'], $branchRow['months'][10]);
        $this->assertSame(['planned' => '1500000.00', 'invoiced' => '0.00', 'paid' => '0.00'], $branchRow['months'][11]);
        $this->assertSame('3000000.00', $branchRow['total']['planned']);
        $this->assertSame('3100000.00', $costs['total']['planned']);
        $byType = collect($this->getJson('/api/v1/service-costs?year=2026&group_by=type')->assertOk()->json('data.rows'))->pluck('key.id')->sort()->values()->all();
        $this->assertSame(['internet', 'telephony'], $byType);

        $this->actingAs($this->accountant);
        $this->assertCount(1, $this->getJson('/api/v1/service-costs?year=2026&group_by=provider')->json('data.rows'));
        $this->assertCount(1, $this->getJson('/api/v1/sim-report')->json('data.rows'));
    }

    public function test_exports_apply_scope_and_never_contain_the_iccid(): void
    {
        $card = $this->simIn($this->branch, ['iccid' => '89998011234567890123']);
        $this->simIn($this->other);
        $this->serviceIn($this->branch, ['started_at' => '2026-01-01']);
        $this->serviceIn($this->other, ['started_at' => '2026-01-01']);
        app(BillingService::class)->generate(CarbonImmutable::today());
        Auth::logout();

        $sims = iterator_to_array(app(SimCardReport::class)->rows($this->accountant, [], 'uz'), false);
        $this->assertSame([$card->phone_number], array_column($sims, 0));
        $this->assertStringNotContainsString('89998011234567890123', json_encode($sims) ?: '');
        $this->assertCount(2, iterator_to_array(app(ServiceInvoiceReport::class)->rows($this->accountant, [], 'uz'), false));
        $costs = iterator_to_array(app(ServiceCostReport::class)->rows($this->accountant, ['year' => 2026, 'group_by' => 'organization'], 'uz'), false);
        $this->assertSame('Xiva GTS', $costs[0][0]);
    }
}
