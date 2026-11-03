<?php

namespace Tests\Feature\Helpdesk;

use App\Models\Organization;
use App\Services\Helpdesk\TicketNumberGenerator;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class TicketNumberTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    public function test_numbers_are_sequential_and_unique(): void
    {
        $this->seedHelpdesk();
        $user = $this->makeStaff(Roles::EMPLOYEE, Organization::factory()->create());

        $numbers = collect(range(1, 20))->map(fn () => $this->ticketBy($user)->number);

        $this->assertSame(20, $numbers->unique()->count());
        $this->assertSame('HD-'.now('Asia/Tashkent')->year.'-000020', $numbers->last());
    }

    public function test_counter_restarts_each_year_in_display_timezone(): void
    {
        $generator = app(TicketNumberGenerator::class);

        $this->assertSame('HD-2026-000001', $generator->next(CarbonImmutable::parse('2026-12-31 18:00', 'UTC')));
        $this->assertSame('HD-2026-000002', $generator->next(CarbonImmutable::parse('2026-12-31 18:30', 'UTC')));
        // 2026-12-31 19:00 UTC is already 2027-01-01 00:00 in Tashkent.
        $this->assertSame('HD-2027-000001', $generator->next(CarbonImmutable::parse('2026-12-31 19:00', 'UTC')));
    }
}
