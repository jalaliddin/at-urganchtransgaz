<?php

namespace Tests\Feature\Printers;

use App\Models\Asset;
use App\Models\Organization;
use App\Models\PrinterCounter;
use App\Models\User;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesPrinters;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class PrinterCounterTest extends TestCase
{
    use CreatesPrinters, InteractsWithAccess, RefreshDatabase;

    private Organization $branch;

    private User $it;

    private Asset $printer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPrinters();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'Asia/Tashkent'));
        $this->branch = Organization::factory()->create();
        $head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->branch, true);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->branch);
        $this->printer = $this->printerIn($this->branch, $head);
    }

    /** @param  array<string, mixed>  $extra */
    private function read(string $date, int $total, array $extra = []): TestResponse
    {
        return $this->actingAs($this->it)->postJson("/api/v1/printers/{$this->printer->id}/counters", ['read_at' => $date, 'total' => $total, ...$extra]);
    }

    public function test_readings_keep_the_counter_order_unless_the_counter_was_reset(): void
    {
        $this->read('2026-10-01', 1000)->assertCreated()->assertJsonPath('data.total', 1000)->assertJsonPath('data.source', 'manual');
        $this->read('2026-11-01', 1500)->assertCreated();
        $this->read('2026-10-15', 1600)->assertStatus(422)->assertJsonValidationErrors('total');
        $this->read('2026-10-15', 900)->assertStatus(422)->assertJsonValidationErrors('total');
        $this->read('2026-10-15', 1200)->assertCreated();
        $this->read('2026-11-05', 100)->assertStatus(422)->assertJsonValidationErrors('total');
        $this->read('2026-11-05', 100, ['is_reset' => true, 'note' => 'Plata almashtirildi'])->assertCreated();
        $this->read('2026-11-21', 200)->assertStatus(422)->assertJsonValidationErrors('read_at');

        $rows = $this->getJson("/api/v1/printers/{$this->printer->id}/counters")->assertOk()->json('data');
        $this->assertSame([[100, 100, true], [1500, 300, false], [1200, 200, false], [1000, null, false]],
            array_map(fn ($r) => [$r['total'], $r['pages'], $r['is_reset']], $rows));
        $this->assertSame($this->it->id, $rows[0]['created_by']['id']);
    }

    public function test_monthly_pages_follow_the_readings_and_deleting_one_recomputes_them(): void
    {
        $this->read('2026-09-28', 1000);
        $this->read('2026-10-10', 1400);
        $late = $this->read('2026-10-30', 1900)->json('data.id');
        $this->read('2026-11-15', 2500);

        $months = collect($this->getJson("/api/v1/printers/{$this->printer->id}")->assertOk()->json('data.monthly_pages'))->pluck('pages', 'month');
        $this->assertSame('2025-12', $months->keys()->first());
        $this->assertSame(['2026-09' => null, '2026-10' => 900, '2026-11' => 600], $months->only(['2026-09', '2026-10', '2026-11'])->all());

        $this->deleteJson("/api/v1/printer-counters/{$late}")->assertOk();
        $months = collect($this->getJson("/api/v1/printers/{$this->printer->id}")->json('data.monthly_pages'))->pluck('pages', 'month');
        $this->assertSame(['2026-10' => 400, '2026-11' => 1100], $months->only(['2026-10', '2026-11'])->all());
    }

    public function test_deleting_a_reset_that_would_break_the_order_is_refused_and_others_cannot_delete(): void
    {
        $this->read('2026-10-01', 5000);
        $reset = $this->read('2026-10-10', 50, ['is_reset' => true])->json('data.id');
        $this->read('2026-10-20', 800);

        $this->deleteJson("/api/v1/printer-counters/{$reset}")->assertStatus(422)->assertJsonValidationErrors('total');
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->branch))->deleteJson("/api/v1/printer-counters/{$reset}")->assertForbidden();
        $outsider = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create());
        $this->actingAs($outsider)->deleteJson("/api/v1/printer-counters/{$reset}")->assertNotFound();
        $this->actingAs($outsider)->postJson("/api/v1/printers/{$this->printer->id}/counters", ['read_at' => '2026-11-01', 'total' => 900])->assertNotFound();
        $this->assertSame(3, PrinterCounter::query()->withoutGlobalScopes()->count());
    }
}
