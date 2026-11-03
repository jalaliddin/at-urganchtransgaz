<?php

namespace Tests\Feature\Seeders;

use App\Models\TicketCategory;
use App\Models\TicketSlaPolicy;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\HelpdeskSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpdeskSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_spec_categories_and_default_policies_idempotently(): void
    {
        $this->seed(HelpdeskSeeder::class);
        $this->seed(HelpdeskSeeder::class);

        $this->assertSame(
            ['computer', 'printer', 'network', 'phone', 'software', 'account', 'server', 'security', 'other'],
            TicketCategory::orderBy('sort_order')->pluck('code')->all(),
        );
        $this->assertSame('Kompyuter', TicketCategory::where('code', 'computer')->value('name'));

        $policies = TicketSlaPolicy::whereNull('category_id')->get()->keyBy(fn ($p) => $p->priority->value);
        $this->assertCount(4, $policies);
        $this->assertSame([30, 240], [$policies['critical']->response_minutes, $policies['critical']->resolution_minutes]);
        $this->assertSame([60, 480], [$policies['high']->response_minutes, $policies['high']->resolution_minutes]);
        $this->assertSame([120, 1440], [$policies['normal']->response_minutes, $policies['normal']->resolution_minutes]);
        $this->assertSame([480, 2400], [$policies['low']->response_minutes, $policies['low']->resolution_minutes]);
    }

    public function test_reseed_keeps_admin_edits(): void
    {
        $this->seed(HelpdeskSeeder::class);
        TicketCategory::where('code', 'computer')->update(['name' => 'Kompyuterlar', 'is_active' => false]);
        TicketSlaPolicy::whereNull('category_id')->where('priority', 'high')->update(['response_minutes' => 45]);

        $this->seed(HelpdeskSeeder::class);

        $this->assertSame('Kompyuterlar', TicketCategory::where('code', 'computer')->value('name'));
        $this->assertSame(45, TicketSlaPolicy::whereNull('category_id')->where('priority', 'high')->value('response_minutes'));
    }

    public function test_runs_in_production_through_database_seeder(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame(9, TicketCategory::count());
    }
}
