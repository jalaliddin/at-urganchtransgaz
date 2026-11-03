<?php

namespace Tests\Concerns;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\Helpdesk\TicketService;
use Database\Seeders\HelpdeskSeeder;

/**
 * Helpdesk fixtures. Requires InteractsWithAccess.
 */
trait CreatesTickets
{
    protected function seedHelpdesk(): void
    {
        $this->seedRbac();
        $this->seed(HelpdeskSeeder::class);
    }

    /** A user of $role linked to a new employee record of $organization. */
    protected function makeStaff(string $role, Organization $organization, bool $descendants = false): User
    {
        $user = $this->makeUser($role, $organization, $descendants);
        $employee = Employee::factory()->create(['organization_id' => $organization->id]);
        $user->forceFill(['employee_id' => $employee->id])->save();

        return $user->fresh();
    }

    protected function category(string $code = 'computer'): TicketCategory
    {
        return TicketCategory::where('code', $code)->firstOrFail();
    }

    /** @param  array<string, mixed>  $attributes */
    protected function ticketBy(User $requester, array $attributes = []): Ticket
    {
        return app(TicketService::class)->create([
            'title' => 'Printer ishlamayapti',
            'description' => 'Ikkinchi qavatdagi printer qog\'ozni tortmayapti.',
            'category_id' => $this->category('printer')->id,
            ...$attributes,
        ], $requester);
    }
}
