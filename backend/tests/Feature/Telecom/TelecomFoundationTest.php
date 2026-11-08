<?php

namespace Tests\Feature\Telecom;

use App\Models\ItService;
use App\Models\Organization;
use App\Models\Role;
use App\Models\ServiceInvoice;
use App\Models\ServiceProvider;
use App\Services\Settings\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\TelecomSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TelecomFoundationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, list<string>}> */
    public static function defaults(): array
    {
        return [
            'central head sim' => ['central-it-head', 'sim', ['view', 'manage', 'assign', 'export']],
            'central head services' => ['central-it-head', 'services', ['view', 'manage', 'invoice', 'approve', 'pay', 'export']],
            'central staff sim' => ['central-it-staff', 'sim', ['view', 'manage', 'assign', 'export']],
            'central staff services' => ['central-it-staff', 'services', ['view', 'manage', 'invoice', 'export']],
            'org it sim' => ['org-it-responsible', 'sim', ['view', 'assign', 'export']],
            'org it services' => ['org-it-responsible', 'services', ['view', 'invoice', 'export']],
            'org head sim' => ['org-head', 'sim', ['view', 'export']],
            'org head services' => ['org-head', 'services', ['view', 'export']],
            'accountant sim' => ['accountant', 'sim', ['view', 'export']],
            'accountant services' => ['accountant', 'services', ['view', 'invoice', 'pay', 'export']],
            'auditor sim' => ['auditor', 'sim', ['view', 'export']],
            'auditor services' => ['auditor', 'services', ['view', 'export']],
            'employee sim' => ['employee', 'sim', []],
            'employee services' => ['employee', 'services', []],
        ];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('defaults')]
    public function test_default_roles_get_module_permissions(string $role, string $module, array $expected): void
    {
        $this->seed(RbacSeeder::class);

        $granted = Role::findByName($role, 'web')->permissions->pluck('name')
            ->filter(fn (string $p) => str_starts_with($p, $module.'.'))
            ->map(fn (string $p) => substr($p, strlen($module) + 1))->sort()->values()->all();

        sort($expected);
        $this->assertSame($expected, $granted);
    }

    public function test_seeder_creates_the_mobile_operators_idempotently_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();
        ServiceProvider::query()->where('name', 'Beeline')->update(['phone' => '+998 90 000 00 00']);
        $this->seed(TelecomSeeder::class);

        $this->assertSame(['Beeline', 'Mobiuz', 'Ucell', 'Uzmobile'], ServiceProvider::query()->where('is_mobile_operator', true)->orderBy('name')->pluck('name')->all());
        $this->assertSame('+998 90 000 00 00', ServiceProvider::query()->where('name', 'Beeline')->value('phone'));
    }

    public function test_a_payment_must_be_positive(): void
    {
        $organization = Organization::factory()->create();
        $provider = ServiceProvider::query()->create(['name' => 'Sarkor Telecom']);
        $service = ItService::query()->create(['organization_id' => $organization->id, 'provider_id' => $provider->id, 'type' => 'internet', 'name' => 'Internet 100 Mbit']);
        $invoice = ServiceInvoice::query()->create(['service_id' => $service->id, 'organization_id' => $organization->id, 'kind' => 'extra',
            'period_start' => '2026-11-01', 'period_end' => '2026-11-30', 'due_date' => '2026-11-10', 'status' => 'planned']);
        DB::table('service_payments')->insert(['invoice_id' => $invoice->id, 'amount' => '1.00', 'paid_at' => '2026-11-08']);

        $this->expectException(QueryException::class);
        DB::table('service_payments')->insert(['invoice_id' => $invoice->id, 'amount' => '0.00', 'paid_at' => '2026-11-08']);
    }

    public function test_due_notice_setting_defaults_to_five_days(): void
    {
        $this->assertSame(5, app(SettingsService::class)->get('services.due_notice_days'));
    }
}
