<?php

namespace Tests\Feature\Telecom;

use App\Enums\InvoiceStatus;
use App\Models\ItService;
use App\Models\Organization;
use App\Models\ServiceInvoice;
use App\Models\User;
use App\Services\Telecom\BillingService;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesTelecom;
use Tests\Concerns\InteractsWithAccess;
use Tests\Feature\Files\SafeUploadTest;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use CreatesTelecom, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $head;

    private User $it;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->travelTo(CarbonImmutable::parse('2026-11-08 10:00', 'Asia/Tashkent'));
        $this->center = Organization::factory()->create();
        $this->branch = Organization::factory()->create(['parent_id' => $this->center->id]);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->branch);
        $this->accountant = $this->makeUser(Roles::ACCOUNTANT, $this->branch);
    }

    /** @return array<string, array{string, string, string|null, string, string}> */
    public static function statuses(): array
    {
        // period start, due date, invoiced amount, paid, expected (today = 2026-11-08)
        return [
            'next period' => ['2026-12-01', '2026-12-10', null, '0', 'planned'],
            'period started, no invoice' => ['2026-11-01', '2026-11-10', null, '0', 'awaiting_invoice'],
            'invoice entered' => ['2026-11-01', '2026-11-10', '100.00', '0', 'due'],
            'part paid' => ['2026-11-01', '2026-11-10', '100.00', '40.00', 'partially_paid'],
            'paid' => ['2026-11-01', '2026-11-10', '100.00', '100.00', 'paid'],
            'zero invoice' => ['2026-11-01', '2026-11-10', '0.00', '0', 'paid'],
            'past due, no invoice' => ['2026-10-01', '2026-10-10', null, '0', 'overdue'],
            'past due, part paid' => ['2026-10-01', '2026-10-10', '100.00', '40.00', 'overdue'],
            'past due, paid' => ['2026-10-01', '2026-10-10', '100.00', '100.00', 'paid'],
            'due today' => ['2026-11-01', '2026-11-08', '100.00', '0', 'due'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_status_is_derived_from_dates_invoice_and_payments(string $start, string $due, ?string $amount, string $paid, string $expected): void
    {
        $today = CarbonImmutable::parse('2026-11-08');
        $this->assertSame($expected, InvoiceStatus::derive(CarbonImmutable::parse($start), CarbonImmutable::parse($due), $amount, $paid, $today)->value);
    }

    public function test_periods_are_generated_for_the_current_and_next_period_once(): void
    {
        $monthly = $this->serviceIn($this->branch, ['started_at' => '2026-01-10', 'due_day' => 15]);
        $quarterly = $this->serviceIn($this->branch, ['name' => 'Xosting', 'type' => 'data_center', 'billing_cycle' => 'quarterly', 'fee' => '900000', 'started_at' => '2025-01-01']);
        // No start date: tracking starts today, so periods already due before today are not generated.

        $yearly = $this->serviceIn($this->branch, ['name' => 'Domen', 'type' => 'other', 'billing_cycle' => 'yearly', 'fee' => '120000', 'due_day' => 1]);
        $this->serviceIn($this->branch, ['name' => 'Eski', 'is_active' => false]);
        $this->serviceIn($this->branch, ['name' => 'Tugagan', 'ended_at' => '2026-09-30']);
        $this->serviceIn($this->branch, ['name' => 'Kelgusi yil', 'started_at' => '2027-02-01']);

        $billing = app(BillingService::class);
        $billing->generate(CarbonImmutable::parse('2026-11-08'));
        $billing->generate(CarbonImmutable::parse('2026-11-08'));

        $rows = fn (ItService $s) => ServiceInvoice::query()->where('service_id', $s->id)->orderBy('period_start')->get()
            ->map(fn (ServiceInvoice $i) => [$i->period_start->toDateString(), $i->period_end->toDateString(), $i->due_date->toDateString(), $i->planned_amount, $i->status->value])->all();
        $this->assertSame([
            ['2026-11-01', '2026-11-30', '2026-11-15', '1500000.00', 'awaiting_invoice'],
            ['2026-12-01', '2026-12-31', '2026-12-15', '1500000.00', 'planned'],
        ], $rows($monthly));
        $this->assertSame([
            ['2026-10-01', '2026-12-31', '2026-10-10', '900000.00', 'overdue'],
            ['2027-01-01', '2027-03-31', '2027-01-10', '900000.00', 'planned'],
        ], $rows($quarterly));
        $this->assertSame([['2027-01-01', '2027-12-31', '2027-01-01', '120000.00', 'planned']], $rows($yearly));
        $this->assertSame(5, ServiceInvoice::query()->count());

        // A new service gets its periods at once.
        $id = $this->actingAs($this->head)->postJson('/api/v1/it-services', ['organization_id' => $this->branch->id, 'provider_id' => $this->provider()->id,
            'type' => 'telephony', 'name' => 'Shahar telefoni', 'fee' => '50000', 'billing_cycle' => 'monthly'])->assertCreated()->json('data.id');
        $this->assertSame(2, ServiceInvoice::query()->where('service_id', $id)->count());
    }

    public function test_an_invoice_is_entered_approved_centrally_and_paid_without_overpayment(): void
    {
        Storage::fake('local');
        $service = $this->serviceIn($this->branch);
        app(BillingService::class)->generate(CarbonImmutable::today());
        $invoice = ServiceInvoice::query()->where('service_id', $service->id)->where('status', 'awaiting_invoice')->firstOrFail();
        $url = "/api/v1/service-invoices/{$invoice->id}";

        $this->actingAs($this->it);
        $this->putJson($url, ['invoice_number' => 'HF-1107', 'invoice_date' => '2026-11-05', 'amount' => '1520000'])->assertOk()
            ->assertJsonPath('data.status', 'due')->assertJsonPath('data.amount', '1520000.00')->assertJsonPath('data.approved_at', null);
        $this->post("{$url}/file", ['file' => UploadedFile::fake()->createWithContent('hf.pdf', SafeUploadTest::pdf())], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.file.name', 'hf.pdf');
        $this->postJson("{$url}/approve")->assertForbidden();
        $this->actingAs($this->accountant)->postJson("{$url}/payments", ['amount' => '100', 'paid_at' => '2026-11-07'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->actingAs($this->head)->postJson("{$url}/approve")->assertOk()->assertJsonPath('data.approved_by.id', $this->head->id);
        $this->postJson("{$url}/approve")->assertStatus(422)->assertJsonPath('code', 'invalid_transition');

        $this->actingAs($this->it)->postJson("{$url}/payments", ['amount' => '100', 'paid_at' => '2026-11-07'])->assertForbidden();
        $this->actingAs($this->accountant);
        $payment = $this->postJson("{$url}/payments", ['amount' => '520000', 'paid_at' => '2026-11-07', 'document_number' => 'PP-77'])->assertCreated()
            ->assertJsonPath('data.status', 'partially_paid')->assertJsonPath('data.paid_amount', '520000.00')->json('data.payments.0.id');
        $this->postJson("{$url}/payments", ['amount' => '1000000.01', 'paid_at' => '2026-11-08'])->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->postJson("{$url}/payments", ['amount' => '100', 'paid_at' => '2026-11-09'])->assertStatus(422)->assertJsonValidationErrors('paid_at');
        $this->actingAs($this->it)->putJson($url, ['amount' => '500000'])->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->actingAs($this->accountant)->postJson("{$url}/payments", ['amount' => '1000000', 'paid_at' => '2026-11-08'])->assertCreated()
            ->assertJsonPath('data.status', 'paid')->assertJsonPath('data.paid_amount', '1520000.00');

        $this->deleteJson("/api/v1/service-payments/{$payment}")->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('data.status', 'partially_paid')->assertJsonPath('data.paid_amount', '1000000.00');

        // Changing the amount after approval needs a new approval.
        $this->actingAs($this->it)->putJson($url, ['amount' => '1600000'])->assertOk()->assertJsonPath('data.approved_at', null);
        $this->actingAs($this->accountant)->postJson("{$url}/payments", ['amount' => '1', 'paid_at' => '2026-11-08'])->assertStatus(422);

        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00', 'Asia/Tashkent'));
        app(BillingService::class)->refreshOpen(CarbonImmutable::today());
        $this->assertSame(InvoiceStatus::Overdue, $invoice->refresh()->status);
    }

    public function test_extra_costs_are_separate_invoices_and_only_they_can_be_deleted(): void
    {
        $service = $this->serviceIn($this->branch);
        app(BillingService::class)->generate(CarbonImmutable::today());
        $regular = ServiceInvoice::query()->where('service_id', $service->id)->firstOrFail();

        $this->actingAs($this->it);
        $extra = $this->postJson("/api/v1/it-services/{$service->id}/invoices", ['description' => 'Router o\'rnatish', 'amount' => '350000',
            'invoice_date' => '2026-11-06', 'due_date' => '2026-11-20', 'invoice_number' => 'HF-1108'])->assertCreated()
            ->assertJsonPath('data.kind', 'extra')->assertJsonPath('data.status', 'due')->assertJsonPath('data.period_start', '2026-11-01')->json('data.id');
        $this->postJson("/api/v1/it-services/{$service->id}/invoices", ['amount' => '1'])->assertStatus(422)->assertJsonValidationErrors(['description', 'due_date']);

        $this->actingAs($this->head);
        $this->deleteJson("/api/v1/service-invoices/{$regular->id}")->assertStatus(409);
        $this->deleteJson("/api/v1/service-invoices/{$extra}")->assertOk();
    }

    public function test_invoices_are_listed_within_scope_with_filters_and_totals(): void
    {
        $internet = $this->serviceIn($this->branch);
        $phone = $this->serviceIn($this->branch, ['name' => 'Telefon', 'type' => 'telephony', 'fee' => '50000', 'provider_id' => $this->provider('Uztelecom')->id]);
        $foreign = $this->serviceIn(Organization::factory()->create());
        app(BillingService::class)->generate(CarbonImmutable::today());
        $foreignInvoice = ServiceInvoice::query()->withoutGlobalScopes()->where('service_id', $foreign->id)->firstOrFail();

        $this->actingAs($this->it);
        $this->getJson('/api/v1/service-invoices')->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonPath('summary.planned', '3100000.00')->assertJsonPath('summary.paid', '0.00');
        $this->getJson('/api/v1/service-invoices?filter[period]=2026-11')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/service-invoices?filter[status]=planned&filter[type]=telephony')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.service.id', $phone->id);
        $this->getJson("/api/v1/service-invoices?filter[provider]={$internet->provider_id}")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/service-invoices/{$foreignInvoice->id}")->assertNotFound();
        $this->getJson('/api/v1/service-invoices?filter[approval]=pending')->assertOk()->assertJsonCount(0, 'data');
    }
}
