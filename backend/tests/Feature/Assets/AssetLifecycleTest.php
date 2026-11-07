<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Assets\AssetDisposalRequestedNotification;
use App\Notifications\Assets\AssetWarrantyExpiringNotification;
use App\Support\Permissions\Roles;
use Database\Seeders\HelpdeskSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class AssetLifecycleTest extends TestCase
{
    use CreatesAssets, CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private User $it;

    private User $head;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAssets();
        $this->seed(HelpdeskSeeder::class);
        $this->org = Organization::factory()->create();
        $this->it = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org);
        $this->asset = $this->assetIn($this->org, $this->it);
    }

    private function url(string $suffix): string
    {
        return "/api/v1/assets/{$this->asset->id}{$suffix}";
    }

    public function test_repair_puts_asset_in_repair_and_restores_the_previous_status(): void
    {
        $employee = Employee::factory()->create(['organization_id' => $this->org->id]);
        $ticket = $this->ticketBy($this->it);
        $foreignTicket = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, Organization::factory()->create()));
        $this->actingAs($this->it);
        $this->postJson($this->url('/assign'), ['employee_id' => $employee->id])->assertOk();

        $this->postJson($this->url('/maintenances'), ['description' => 'Quvvat bloki', 'ticket_id' => $foreignTicket->id])
            ->assertStatus(422)->assertJsonValidationErrors('ticket_id');
        $id = $this->postJson($this->url('/maintenances'), ['description' => 'Quvvat bloki ishlamaydi', 'vendor' => 'Servis markazi', 'ticket_id' => $ticket->id])
            ->assertCreated()->assertJsonPath('data.status', 'open')->json('data.id');
        $this->assertSame('in_repair', $this->asset->fresh()->status->value);
        $this->postJson($this->url('/maintenances'), ['description' => 'Yana'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->postJson($this->url('/status'), ['status' => 'reserve'])->assertStatus(422);

        $this->postJson($this->url("/maintenances/{$id}/close"), ['result' => 'Quvvat bloki almashtirildi', 'cost' => 450000])
            ->assertOk()->assertJsonPath('data.status', 'closed');
        $asset = $this->asset->fresh();
        $this->assertSame('in_use', $asset->status->value);
        $this->assertSame($employee->id, $asset->holder_employee_id);
        $this->assertCount(1, $this->getJson($this->url('/maintenances'))->json('data'));
        $this->assertDatabaseHas('asset_events', ['asset_id' => $asset->id, 'action' => 'maintenance_closed']);
    }

    public function test_write_off_needs_approval(): void
    {
        Notification::fake();
        $this->actingAs($this->it);

        $id = $this->postJson($this->url('/disposals'), ['reason' => 'Ta\'mirlab bo\'lmaydi', 'act_number' => 'HCh-12'])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        Notification::assertSentTo($this->head, AssetDisposalRequestedNotification::class);
        $this->postJson($this->url('/disposals'), ['reason' => 'Yana'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->postJson("/api/v1/asset-disposals/{$id}/approve")->assertForbidden();

        $this->actingAs($this->head);
        $this->assertCount(1, $this->getJson('/api/v1/asset-disposals?filter[status]=pending')->assertOk()->json('data'));
        $this->postJson("/api/v1/asset-disposals/{$id}/approve", ['note' => 'Tasdiqlandi'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame('written_off', $this->asset->fresh()->status->value);
        $this->patchJson($this->url(''), ['name' => 'X'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->postJson("/api/v1/asset-disposals/{$id}/reject", ['note' => 'X'])->assertStatus(422);
    }

    public function test_rejection_keeps_the_asset_and_requires_a_reason(): void
    {
        $this->actingAs($this->it);
        $id = $this->postJson($this->url('/disposals'), ['reason' => 'Eskirgan'])->json('data.id');

        $this->actingAs($this->head);
        $this->postJson("/api/v1/asset-disposals/{$id}/reject")->assertStatus(422)->assertJsonValidationErrors('note');
        $this->postJson("/api/v1/asset-disposals/{$id}/reject", ['note' => 'Hali ishlaydi'])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertSame('in_stock', $this->asset->fresh()->status->value);

        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, Organization::factory()->create()));
        $this->assertSame([], $this->getJson('/api/v1/asset-disposals')->json('data'));
        $this->postJson("/api/v1/asset-disposals/{$id}/approve")->assertNotFound();
    }

    public function test_warranty_reminder_is_sent_once_per_warranty_date(): void
    {
        Notification::fake();
        $this->asset->forceFill(['warranty_until' => now()->addDays(10)->toDateString()])->save();
        $this->assetIn($this->org, $this->it, ['warranty_until' => now()->addDays(40)->toDateString()]);
        $this->assetIn($this->org, $this->it, ['warranty_until' => now()->subDay()->toDateString()]);

        $this->artisan('assets:warranty-check')->assertSuccessful();
        $this->artisan('assets:warranty-check')->assertSuccessful();

        Notification::assertSentToTimes($this->it, AssetWarrantyExpiringNotification::class, 1);
        Notification::assertSentTo($this->it, AssetWarrantyExpiringNotification::class,
            fn ($n, $c, $u) => $n->toArray($u)['params']['count'] === 1);

        $this->asset->forceFill(['warranty_until' => now()->addDays(20)->toDateString()])->save();
        $this->artisan('assets:warranty-check')->assertSuccessful();
        Notification::assertSentToTimes($this->it, AssetWarrantyExpiringNotification::class, 2);
    }
}
