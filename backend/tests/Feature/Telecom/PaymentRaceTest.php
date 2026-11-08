<?php

namespace Tests\Feature\Telecom;

use App\Models\ItService;
use App\Models\Organization;
use App\Models\ServiceInvoice;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Services\Telecom\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Two payments against the same invoice on separate MySQL connections (committed fixtures), interleaved like
 * a real race: B has read inside its transaction (REPEATABLE READ snapshot) before A commits. Together they
 * must never exceed the invoiced amount.
 */
class PaymentRaceTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['aux', 'aux_a', 'aux_b'] as $name) {
            config(["database.connections.{$name}" => config('database.connections.'.config('database.default'))]);
        }
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection(config('database.default'));
        $aux = DB::connection('aux');
        if (isset($this->ids['invoice'])) {
            $payments = $aux->table('service_payments')->where('invoice_id', $this->ids['invoice']);
            // Audit rows committed by the worker connections only (read without locks, deleted by primary key):
            // the test's own connection still holds its rolled-back-later rows.
            $audit = $aux->table('audit_logs')->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('auditable_type', 'service_payment')->whereIn('auditable_id', (clone $payments)->pluck('id')))
                ->orWhere(fn ($w) => $w->where('auditable_type', 'service_invoice')->where('auditable_id', $this->ids['invoice'])))->pluck('id');
            $aux->table('audit_logs')->whereIn('id', $audit)->delete();
            $payments->delete();
            $aux->table('service_invoices')->where('id', $this->ids['invoice'])->delete();
            $aux->table('it_services')->where('id', $this->ids['service'])->delete();
            $aux->table('service_providers')->where('id', $this->ids['provider'])->delete();
            $aux->table('users')->where('id', $this->ids['user'])->delete();
            $aux->table('organizations')->where('id', $this->ids['organization'])->delete();
        }
        foreach (['aux_b', 'aux_a', 'aux'] as $name) {
            DB::connection($name)->disconnect();
        }
        parent::tearDown();
    }

    public function test_a_payment_committed_after_the_snapshot_is_still_counted(): void
    {
        $this->committedFixture();
        $service = app(InvoiceService::class);
        $user = User::on('aux')->findOrFail($this->ids['user']);
        $payment = ['amount' => '60.00', 'paid_at' => now()->toDateString()];

        $b = DB::connection('aux_b');
        $b->beginTransaction();
        $b->table('service_payments')->where('invoice_id', $this->ids['invoice'])->sum('amount');

        DB::setDefaultConnection('aux_a');
        $service->pay(ServiceInvoice::on('aux')->withoutGlobalScopes()->findOrFail($this->ids['invoice']), $payment, $user);

        DB::setDefaultConnection('aux_b');
        try {
            $service->pay(ServiceInvoice::on('aux')->withoutGlobalScopes()->findOrFail($this->ids['invoice']), $payment, $user);
            $refused = false;
        } catch (ValidationException) {
            $refused = true;
        } finally {
            $b->rollBack();
        }

        $this->assertTrue($refused, 'the second payment would exceed the invoice');
        $this->assertSame('60.00', DB::connection('aux')->table('service_invoices')->where('id', $this->ids['invoice'])->value('paid_amount'));
    }

    private function committedFixture(): void
    {
        $organization = Organization::factory()->connection('aux')->create();
        $user = User::factory()->connection('aux')->create(['organization_id' => $organization->id]);
        $provider = ServiceProvider::on('aux')->create(['name' => 'Race provider '.getmypid()]);
        $service = ItService::on('aux')->withoutGlobalScopes()->create(['organization_id' => $organization->id, 'provider_id' => $provider->id,
            'type' => 'internet', 'name' => 'Race', 'fee' => '100.00', 'billing_cycle' => 'monthly']);
        $invoice = ServiceInvoice::on('aux')->withoutGlobalScopes()->create(['service_id' => $service->id, 'organization_id' => $organization->id,
            'kind' => 'extra', 'period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->endOfMonth()->toDateString(),
            'amount' => '100.00', 'due_date' => now()->addDays(5)->toDateString(), 'status' => 'due', 'approved_by' => $user->id, 'approved_at' => now()]);
        $this->ids = ['organization' => $organization->id, 'user' => $user->id, 'provider' => $provider->id, 'service' => $service->id, 'invoice' => $invoice->id];
    }
}
